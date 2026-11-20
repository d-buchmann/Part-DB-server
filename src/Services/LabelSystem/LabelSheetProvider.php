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

namespace App\Services\LabelSystem;

use App\Entity\LabelSystem\LabelOptions;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * Knows the built-in label sheets (config/label_sheets.yaml) and computes where labels go on a sheet
 * (page size and the position of every label on it, all in mm).
 * @see \App\Tests\Services\LabelSystem\LabelSheetProviderTest
 */
final class LabelSheetProvider
{
    /** Page formats that can be referenced by name in the sheet file, [width, height] in mm */
    public const PAGE_FORMATS = ['A4' => [210.0, 297.0], 'A5' => [148.0, 210.0], 'Letter' => [215.9, 279.4], 'Legal' => [215.9, 355.6]];

    private const UNITS = ['mm' => 1.0, 'in' => 25.4];

    /** @var array<string, array{brand: string, name: string, page: array{float, float}, label: array{float, float}, grid: array{columns: int, rows: int, left: float, top: float, pitch_x: float, pitch_y: float}}>|null */
    private ?array $sheets = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/config/label_sheets.yaml')]
        private readonly string $sheetsFile,
    ) {
    }

    /**
     * All built-in sheets, keyed by "brand/code", with all lengths converted to mm.
     * @return array<string, array{brand: string, name: string, page: array{float, float}, label: array{float, float}, grid: array{columns: int, rows: int, left: float, top: float, pitch_x: float, pitch_y: float}}>
     */
    public function getSheets(): array
    {
        if ($this->sheets !== null) {
            return $this->sheets;
        }

        $this->sheets = [];
        foreach (Yaml::parseFile($this->sheetsFile) as $brand => $products) {
            foreach ($products as $code => $sheet) {
                $unit = $sheet['unit'] ?? 'mm';
                $factor = self::UNITS[$unit] ?? throw new \InvalidArgumentException("Label sheet $brand/$code: unknown unit '$unit'");
                $page_name = is_string($sheet['page']) ? $sheet['page'] : null;

                $this->sheets["$brand/$code"] = [
                    'brand' => (string) $brand,
                    'name' => sprintf(
                        '%s – %d × %s × %s %s (%s)',
                        implode(' / ', [$code, ...($sheet['also'] ?? [])]),
                        $sheet['columns'] * $sheet['rows'],
                        $sheet['label'][0],
                        $sheet['label'][1],
                        $unit,
                        $page_name ?? sprintf('%s × %s %s', $sheet['page'][0], $sheet['page'][1], $unit)
                    ),
                    'page' => $page_name !== null
                        ? (self::PAGE_FORMATS[$page_name] ?? throw new \InvalidArgumentException("Label sheet $brand/$code: unknown page format '$page_name'"))
                        : [$sheet['page'][0] * $factor, $sheet['page'][1] * $factor],
                    'label' => [$sheet['label'][0] * $factor, $sheet['label'][1] * $factor],
                    'grid' => [
                        'columns' => (int) $sheet['columns'],
                        'rows' => (int) $sheet['rows'],
                        'left' => $sheet['left'] * $factor,
                        'top' => $sheet['top'] * $factor,
                        'pitch_x' => $sheet['pitch_x'] * $factor,
                        'pitch_y' => $sheet['pitch_y'] * $factor,
                    ],
                ];
            }
        }

        return $this->sheets;
    }

    /**
     * @return array{brand: string, name: string, page: array{float, float}, label: array{float, float}, grid: array{columns: int, rows: int, left: float, top: float, pitch_x: float, pitch_y: float}}|null
     */
    public function getSheet(string $key): ?array
    {
        return $this->getSheets()[$key] ?? null;
    }

    /**
     * The sheets as choices for a select, grouped by brand.
     * @return array<string, array<string, string>>
     */
    public function getChoices(): array
    {
        $choices = [];
        foreach ($this->getSheets() as $key => $sheet) {
            $choices[$sheet['brand']][$sheet['name']] = $key;
        }

        return $choices;
    }

    /**
     * Returns the sheet layout for the given options, or null if every label is its own page of the label size
     * (the classic single label mode, used by label printers).
     *
     * @return array{page: array{float, float}, label: array{float, float}, slots: list<array{float, float}>}|null
     *     page and label are [width, height], slots the [left, top] position of every label on the page, in reading order
     */
    public function getLayout(LabelOptions $options): ?array
    {
        $sheet = $options->getSheet() !== '' ? $this->getSheet($options->getSheet()) : null;
        if ($sheet !== null) {
            return ['page' => $sheet['page'], 'label' => $sheet['label'], 'slots' => $this->gridSlots($sheet['grid'])];
        }

        $label = [$options->getWidth(), $options->getHeight()];
        $grid = [
            'columns' => $options->getGridColumns(),
            'rows' => $options->getGridRows(),
            'left' => $options->getMarginLeft(),
            'top' => $options->getMarginTop(),
            'pitch_x' => $options->getHorizontalPitch() ?: $label[0],
            'pitch_y' => $options->getVerticalPitch() ?: $label[1],
        ];
        $slots = $this->gridSlots($grid);

        //A page size of 0 means: just big enough for the grid, with the same margin on the opposite side
        $page = [
            $options->getPageWidth() ?: 2 * $grid['left'] + ($grid['columns'] - 1) * $grid['pitch_x'] + $label[0],
            $options->getPageHeight() ?: 2 * $grid['top'] + ($grid['rows'] - 1) * $grid['pitch_y'] + $label[1],
        ];

        if ($slots === [[0.0, 0.0]] && $page === $label) {
            return null;
        }

        return ['page' => $page, 'label' => $label, 'slots' => $slots];
    }

    /**
     * @param  array{columns: int, rows: int, left: float, top: float, pitch_x: float, pitch_y: float}  $grid
     * @return list<array{float, float}>
     */
    private function gridSlots(array $grid): array
    {
        $slots = [];
        for ($row = 0; $row < $grid['rows']; $row++) {
            for ($column = 0; $column < $grid['columns']; $column++) {
                $slots[] = [(float) ($grid['left'] + $column * $grid['pitch_x']), (float) ($grid['top'] + $row * $grid['pitch_y'])];
            }
        }

        return $slots;
    }
}
