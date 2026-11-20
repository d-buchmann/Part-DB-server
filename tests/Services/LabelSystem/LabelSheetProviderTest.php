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
    public function testSingleLabelHasNoLayout(): void
    {
        $this->assertNull((new LabelSheetProvider())->getLayout(new LabelOptions()));
    }

    public function testCustomGrid(): void
    {
        $options = (new LabelOptions())
            ->setWidth(63.5)->setHeight(38.1)
            ->setGridColumns(3)->setGridRows(7)
            ->setPageWidth(210)->setPageHeight(297)
            ->setMarginTop(15.15)->setMarginLeft(7.25)
            ->setHorizontalPitch(66.0)->setVerticalPitch(38.1);

        $layout = (new LabelSheetProvider())->getLayout($options);

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

        $layout = (new LabelSheetProvider())->getLayout($options);

        //Labels butt together, same margin on both sides
        $this->assertSame([[5.0, 0.0], [55.0, 0.0]], $layout['slots']);
        $this->assertSame([110.0, 30.0], $layout['page']);
    }
}
