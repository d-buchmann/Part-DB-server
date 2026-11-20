<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Tests\Services\LabelSystem;

use App\Entity\LabelSystem\LabelOptions;
use App\Services\LabelSystem\LabelSheetProvider;
use PHPUnit\Framework\TestCase;

final class LabelSheetProviderTest extends TestCase
{
    private function provider(): LabelSheetProvider
    {
        return new LabelSheetProvider(__DIR__.'/../../../config/label_sheets.yaml');
    }

    /**
     * Every built-in sheet must be complete, fit on its page and have no overlapping labels.
     */
    public function testBuiltInSheetsAreValid(): void
    {
        $sheets = $this->provider()->getSheets();
        $this->assertNotEmpty($sheets);

        foreach ($sheets as $key => $sheet) {
            $grid = $sheet['grid'];
            [$label_width, $label_height] = $sheet['label'];
            [$page_width, $page_height] = $sheet['page'];

            $this->assertGreaterThan(0, $label_width, $key);
            $this->assertGreaterThan(0, $label_height, $key);
            $this->assertGreaterThanOrEqual(1, $grid['columns'], $key);
            $this->assertGreaterThanOrEqual(1, $grid['rows'], $key);
            //No overlaps (0.01 mm tolerance for rounded manufacturer values)
            $this->assertGreaterThanOrEqual($label_width - 0.01, $grid['pitch_x'], $key);
            $this->assertGreaterThanOrEqual($label_height - 0.01, $grid['pitch_y'], $key);
            //Everything on the page
            $this->assertGreaterThanOrEqual(0, $grid['left'], $key);
            $this->assertGreaterThanOrEqual(0, $grid['top'], $key);
            $this->assertLessThanOrEqual($page_width + 0.01, $grid['left'] + ($grid['columns'] - 1) * $grid['pitch_x'] + $label_width, $key);
            $this->assertLessThanOrEqual($page_height + 0.01, $grid['top'] + ($grid['rows'] - 1) * $grid['pitch_y'] + $label_height, $key);
        }
    }

    public function testChoicesAreGroupedByBrand(): void
    {
        $choices = $this->provider()->getChoices();
        $this->assertSame('Avery/L7160', $choices['Avery']['L7160 / J8160 – 21 × 63.5 × 38.1 mm (A4)']);
    }

    public function testBuiltInSheetIsUsedAndConvertedToMm(): void
    {
        $options = (new LabelOptions())->setSheet('Avery/5160');
        $layout = $this->provider()->getLayout($options);

        $this->assertEqualsWithDelta([215.9, 279.4], $layout['page'], 1e-9);
        $this->assertEqualsWithDelta([66.675, 25.4], $layout['label'], 1e-9);
        $this->assertCount(30, $layout['slots']);
        $this->assertEqualsWithDelta([4.7625 + 69.85, 12.7], $layout['slots'][1], 1e-9);
    }

    public function testSingleLabelHasNoLayout(): void
    {
        $this->assertNull($this->provider()->getLayout(new LabelOptions()));
    }

    public function testCustomGrid(): void
    {
        $options = (new LabelOptions())
            ->setWidth(63.5)->setHeight(38.1)
            ->setGridColumns(3)->setGridRows(7)
            ->setPageWidth(210)->setPageHeight(297)
            ->setMarginTop(15.15)->setMarginLeft(7.25)
            ->setHorizontalPitch(66.0)->setVerticalPitch(38.1);

        $layout = $this->provider()->getLayout($options);

        $this->assertSame([210.0, 297.0], $layout['page']);
        $this->assertSame([63.5, 38.1], $layout['label']);
        $this->assertCount(21, $layout['slots']);
        $this->assertSame([7.25, 15.15], $layout['slots'][0]);
        //Reading order: the second label is right of the first one
        $this->assertSame([73.25, 15.15], $layout['slots'][1]);
        $this->assertEqualsWithDelta([139.25, 15.15 + 6 * 38.1], $layout['slots'][20], 1e-9);
    }

    public function testZeroPageSizeAndPitchAreDerived(): void
    {
        $options = (new LabelOptions())
            ->setWidth(50)->setHeight(30)
            ->setGridColumns(2)->setGridRows(1)
            ->setMarginLeft(5);

        $layout = $this->provider()->getLayout($options);

        //Labels butt together, same margin on both sides
        $this->assertSame([[5.0, 0.0], [55.0, 0.0]], $layout['slots']);
        $this->assertSame([110.0, 30.0], $layout['page']);
    }

    public function testPositionKey(): void
    {
        $provider = $this->provider();

        //Single labels have no sheet position
        $this->assertNull($provider->getPositionKey(new LabelOptions()));
        $this->assertSame('Avery/L7160', $provider->getPositionKey((new LabelOptions())->setSheet('Avery/L7160')));
        $this->assertSame('custom:2x5', $provider->getPositionKey((new LabelOptions())->setGridColumns(2)->setGridRows(5)));

        $this->assertTrue($provider->isValidPositionKey('Avery/L7160'));
        $this->assertTrue($provider->isValidPositionKey('custom:2x5'));
        $this->assertFalse($provider->isValidPositionKey('Avery/nope'));
        $this->assertFalse($provider->isValidPositionKey('custom:2x5; drop'));
    }
}
