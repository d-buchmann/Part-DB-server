<?php
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

declare(strict_types=1);

namespace App\Entity\ProjectSystem;

use Doctrine\DBAL\Types\Types;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\TimestampTrait;
use App\Entity\Parts\Part;
use App\Entity\PriceInformations\Currency;
use App\Validator\Constraints\BigDecimal\BigDecimalPositive;
use App\Validator\Constraints\Selectable;
use Brick\Math\BigDecimal;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The ProjectBOMEntry class represents an entry in a project's BOM.
 */
#[UniqueEntity(fields: ['part', 'project'], message: 'project.bom_entry.part_already_in_bom')]
#[UniqueEntity(fields: ['name', 'project'], message: 'project.bom_entry.name_already_in_bom', ignoreNull: true)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity]
#[ORM\Table('project_bom_entries')]
class ProjectBOMEntry extends AbstractDBElement
{
    use TimestampTrait;

    /**
     * @var float
     */
    #[Assert\Positive]
    #[ORM\Column(type: Types::FLOAT, name: 'quantity')]
    protected float $quantity;

    /**
     * @var string A comma separated list of the names, where this parts should be placed
     */
    #[ORM\Column(type: Types::TEXT, name: 'mountnames')]
    protected string $mountnames = '';

    /**
     * @var string|null An optional name describing this BOM entry (useful for non-part entries)
     */
    #[Assert\Expression('this.getPart() !== null or this.getName() !== null', message: 'validator.project.bom_entry.name_or_part_needed')]
    #[ORM\Column(type: Types::STRING, nullable: true)]
    protected ?string $name = null;

    /**
     * @var string An optional comment for this BOM entry
     */
    #[ORM\Column(type: Types::TEXT)]
    protected string $comment;

    /**
     * @var Project|null
     */
    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'bom_entries')]
    #[ORM\JoinColumn(name: 'id_device')]
    protected ?Project $project = null;

    /**
     * @var Part|null The part associated with this
     */
    #[ORM\ManyToOne(targetEntity: Part::class, inversedBy: 'project_bom_entries')]
    #[ORM\JoinColumn(name: 'id_part')]
    protected ?Part $part = null;

    /**
     * @var BigDecimal|null The price of this non-part BOM entry
     */
    #[Assert\AtLeastOneOf([new BigDecimalPositive(), new Assert\IsNull()])]
    #[ORM\Column(type: 'big_decimal', precision: 11, scale: 5, nullable: true)]
    protected ?BigDecimal $price = null;

    /**
     * @var ?Currency The currency for the price of this non-part BOM entry
     */
    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn]
    #[Selectable]
    protected ?Currency $price_currency = null;

    public function __construct()
    {
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function setQuantity(float $quantity): ProjectBOMEntry
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getMountnames(): string
    {
        return $this->mountnames;
    }

    public function setMountnames(string $mountnames): ProjectBOMEntry
    {
        $this->mountnames = $mountnames;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param  string  $name
     */
    public function setName(?string $name): ProjectBOMEntry
    {
        $this->name = $name;
        return $this;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setComment(string $comment): ProjectBOMEntry
    {
        $this->comment = $comment;
        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): ProjectBOMEntry
    {
        $this->project = $project;
        return $this;
    }



    public function getPart(): ?Part
    {
        return $this->part;
    }

    public function setPart(?Part $part): ProjectBOMEntry
    {
        $this->part = $part;
        return $this;
    }

    /**
     * Returns the price of this BOM entry, if existing.
     * Prices are only valid on non-Part BOM entries.
     */
    public function getPrice(): ?BigDecimal
    {
        return $this->price;
    }

    /**
     * Sets the price of this BOM entry.
     * Prices are only valid on non-Part BOM entries.
     */
    public function setPrice(?BigDecimal $price): void
    {
        $this->price = $price;
    }

    public function getPriceCurrency(): ?Currency
    {
        return $this->price_currency;
    }

    public function setPriceCurrency(?Currency $price_currency): void
    {
        $this->price_currency = $price_currency;
    }

    /**
     * Checks whether this BOM entry is a part associated BOM entry or not.
     * @return bool True if this BOM entry is a part associated BOM entry, false otherwise.
     */
    public function isPartBomEntry(): bool
    {
        return $this->part instanceof Part;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, $payload): void
    {
        //Round quantity to whole numbers, if the part is not a decimal part
        if ($this->part instanceof Part && (!$this->part->getPartUnit() || $this->part->getPartUnit()->isInteger())) {
            $this->quantity = round($this->quantity);
        }
        //Non-Part BOM entries are rounded
        if (!$this->part instanceof Part) {
            $this->quantity = round($this->quantity);
        }

        //Check that every part name in the mountnames list is unique (per bom_entry)
        $mountnames = explode(',', $this->mountnames);
        $mountnames = array_map('trim', $mountnames);
        $uniq_mountnames = array_unique($mountnames);

        //If the number of unique names is not the same as the number of names, there are duplicates
        if (count($mountnames) !== count($uniq_mountnames)) {
            $context->buildViolation('project.bom_entry.mountnames_not_unique')
                ->atPath('mountnames')
                ->addViolation();
        }

        //Check that the number of mountnames is the same as the (rounded) quantity
        if ($this->mountnames !== '' && count($uniq_mountnames) !== (int) round ($this->quantity)) {
            $context->buildViolation('project.bom_entry.mountnames_quantity_mismatch')
                ->atPath('mountnames')
                ->addViolation();
        }

        //Prices are only allowed on non-part BOM entries
        if ($this->part instanceof Part && $this->price instanceof BigDecimal) {
            $context->buildViolation('project.bom_entry.price_not_allowed_on_parts')
                ->atPath('price')
                ->addViolation();
        }

        //Check that the part is not the build representation part of this device or one of its parents
        if ($this->part && $this->part->getBuiltProject() instanceof Project) {
            //Get the associated project
            $associated_project = $this->part->getBuiltProject();
            //Check that it is not the same as the current project neither one of its parents
            $current_project = $this->project;
            while ($current_project) {
                if ($associated_project === $current_project) {
                    $context->buildViolation('project.bom_entry.can_not_add_own_builds_part')
                        ->atPath('part')
                        ->addViolation();
                }
                $current_project = $current_project->getParent();
            }
        }
    }


}
