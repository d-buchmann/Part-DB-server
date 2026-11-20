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

/**
 * Computes where labels go on a sheet (page size and the position of every label on it, all in mm).
 * @see \App\Tests\Services\LabelSystem\LabelSheetProviderTest
 */
final class LabelSheetProvider
{
    /**
     * Returns the sheet layout for the given options, or null if every label is its own page of the label size
     * (the classic single label mode, used by label printers).
     *
     * @return array{page: array{float, float}, label: array{float, float}, slots: list<array{float, float}>}|null
     *     page and label are [width, height], slots the [left, top] position of every label on the page, in reading order
     */
    public function getLayout(LabelOptions $options): ?array
    {
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
