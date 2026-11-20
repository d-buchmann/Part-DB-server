<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Entity\LabelSystem;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Embeddable]
class LabelOptions
{
    /**
     * @var float The page size of the label in mm
     */
    #[Assert\Positive]
    #[ORM\Column(type: Types::FLOAT)]
    #[Groups(["extended", "full", "import"])]
    protected float $width = 50.0;

    /**
     * @var float The page size of the label in mm
     */
    #[Assert\Positive]
    #[ORM\Column(type: Types::FLOAT)]
    #[Groups(["extended", "full", "import"])]
    protected float $height = 30.0;

    /**
     * @var int the number of columns in a grid arrangement
     */
    #[Assert\Positive]
    #[Assert\LessThanOrEqual(100)]
    #[ORM\Column(type: Types::SMALLINT)]
    #[Groups(["extended", "full", "import"])]
    protected int $grid_columns = 1;
    
    /**
     * @var int the number of rows in a grid arrangement
     */
    #[Assert\Positive]
    #[Assert\LessThanOrEqual(100)]
    #[ORM\Column(type: Types::SMALLINT)]
    #[Groups(["extended", "full", "import"])]
    protected int $grid_rows = 1;

    /**
     * @var string The built-in label sheet ("brand/code" from config/label_sheets.yaml) whose geometry is used,
     *  or empty for the custom geometry below
     */
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::STRING, length: 255, options: ['default' => ''])]
    #[Groups(["extended", "full", "import"])]
    protected string $sheet = '';

    /**
     * @var float The width of the page (sheet) in mm. 0 means: derived from the label size and grid
     */
    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.0])]
    #[Groups(["extended", "full", "import"])]
    protected float $page_width = 0.0;

    /**
     * @var float The height of the page (sheet) in mm. 0 means: derived from the label size and grid
     */
    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.0])]
    #[Groups(["extended", "full", "import"])]
    protected float $page_height = 0.0;

    /**
     * @var float The distance from the top page edge to the first label row in mm
     */
    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.0])]
    #[Groups(["extended", "full", "import"])]
    protected float $margin_top = 0.0;

    /**
     * @var float The distance from the left page edge to the first label column in mm
     */
    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.0])]
    #[Groups(["extended", "full", "import"])]
    protected float $margin_left = 0.0;

    /**
     * @var float The distance between the left edges of two neighbouring labels in mm. 0 means: label width (no gap)
     */
    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.0])]
    #[Groups(["extended", "full", "import"])]
    protected float $horizontal_pitch = 0.0;

    /**
     * @var float The distance between the top edges of two neighbouring labels in mm. 0 means: label height (no gap)
     */
    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.0])]
    #[Groups(["extended", "full", "import"])]
    protected float $vertical_pitch = 0.0;

    /**
     * @var BarcodeType The type of the barcode that should be used in the label (e.g. 'qr')
     */
    #[ORM\Column(type: Types::STRING, enumType: BarcodeType::class)]
    #[Groups(["extended", "full", "import"])]
    protected BarcodeType $barcode_type = BarcodeType::NONE;

    /**
     * @var LabelPictureType What image should be shown along the label
     */
    #[ORM\Column(type: Types::STRING, enumType: LabelPictureType::class)]
    #[Groups(["extended", "full", "import"])]
    protected LabelPictureType $picture_type = LabelPictureType::NONE;

    #[ORM\Column(type: Types::STRING, enumType: LabelSupportedElement::class)]
    #[Groups(["extended", "full", "import"])]
    protected LabelSupportedElement $supported_element = LabelSupportedElement::PART;

    /**
     * @var string any additional CSS for the label
     */
    #[ORM\Column(type: Types::TEXT)]
    #[Groups([ "full", "import"])]
    protected string $additional_css = '';

    /** @var LabelProcessMode The mode that will be used to interpret the lines
     */
    #[ORM\Column(name: 'lines_mode', type: Types::STRING, enumType: LabelProcessMode::class)]
    #[Groups(["extended", "full", "import"])]
    protected LabelProcessMode $process_mode = LabelProcessMode::PLACEHOLDER;

    /**
     * @var string
     */
    #[ORM\Column(type: Types::TEXT)]
    #[Groups(["extended", "full", "import"])]
    protected string $lines = '';

    /**
     * @var bool Remember (per user and sheet type) where the next free label position is, and start there next time
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(["extended", "full", "import"])]
    protected bool $remember_position = false;

    /**
     * @var int The number of leading empty labels in a grid arrangement
     * This does not need to be persisted, but can be passed to the label generator as an option
     */
    #[Assert\PositiveOrZero]
    public int $skip_count = 0;
    
    public function getWidth(): float
    {
        return $this->width;
    }

    public function setWidth(float $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    public function setHeight(float $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getGridColumns(): int
    {
        return $this->grid_columns;
    }

    public function setGridColumns(int $col): self
    {
        $this->grid_columns = $col;

        return $this;
    }

    public function getGridRows(): int
    {
        return $this->grid_rows;
    }

    public function setGridRows(int $row): self
    {
        $this->grid_rows = $row;

        return $this;
    }
    
    public function getSheet(): string
    {
        return $this->sheet;
    }

    public function setSheet(?string $sheet): self
    {
        $this->sheet = $sheet ?? '';

        return $this;
    }

    public function getPageWidth(): float
    {
        return $this->page_width;
    }

    public function setPageWidth(float $page_width): self
    {
        $this->page_width = $page_width;

        return $this;
    }

    public function getPageHeight(): float
    {
        return $this->page_height;
    }

    public function setPageHeight(float $page_height): self
    {
        $this->page_height = $page_height;

        return $this;
    }

    public function getMarginTop(): float
    {
        return $this->margin_top;
    }

    public function setMarginTop(float $margin_top): self
    {
        $this->margin_top = $margin_top;

        return $this;
    }

    public function getMarginLeft(): float
    {
        return $this->margin_left;
    }

    public function setMarginLeft(float $margin_left): self
    {
        $this->margin_left = $margin_left;

        return $this;
    }

    public function getHorizontalPitch(): float
    {
        return $this->horizontal_pitch;
    }

    public function setHorizontalPitch(float $horizontal_pitch): self
    {
        $this->horizontal_pitch = $horizontal_pitch;

        return $this;
    }

    public function getVerticalPitch(): float
    {
        return $this->vertical_pitch;
    }

    public function setVerticalPitch(float $vertical_pitch): self
    {
        $this->vertical_pitch = $vertical_pitch;

        return $this;
    }

    public function isRememberPosition(): bool
    {
        return $this->remember_position;
    }

    public function setRememberPosition(bool $remember_position): self
    {
        $this->remember_position = $remember_position;

        return $this;
    }

    public function getSkipCount(): int
    {
        return $this->skip_count;
    }

    public function setSkipCount(int $cnt): self
    {
        $this->skip_count = $cnt;

        return $this;
    }

    public function getBarcodeType(): BarcodeType
    {
        return $this->barcode_type;
    }

    public function setBarcodeType(BarcodeType $barcode_type): self
    {
        $this->barcode_type = $barcode_type;

        return $this;
    }

    public function getPictureType(): LabelPictureType
    {
        return $this->picture_type;
    }

    public function setPictureType(LabelPictureType $picture_type): self
    {
        $this->picture_type = $picture_type;

        return $this;
    }

    public function getSupportedElement(): LabelSupportedElement
    {
        return $this->supported_element;
    }

    public function setSupportedElement(LabelSupportedElement $supported_element): self
    {
        $this->supported_element = $supported_element;

        return $this;
    }

    public function getLines(): string
    {
        return $this->lines;
    }

    public function setLines(string $lines): self
    {
        $this->lines = $lines;

        return $this;
    }

    /**
     * Gets additional CSS (it will simply be attended to base CSS).
     */
    public function getAdditionalCss(): string
    {
        return $this->additional_css;
    }

    public function setAdditionalCss(string $additional_css): self
    {
        $this->additional_css = $additional_css;

        return $this;
    }

    public function getProcessMode(): LabelProcessMode
    {
        return $this->process_mode;
    }

    public function setProcessMode(LabelProcessMode $process_mode): self
    {
        $this->process_mode = $process_mode;

        return $this;
    }
}
