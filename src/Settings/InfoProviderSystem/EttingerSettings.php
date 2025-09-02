<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Settings\InfoProviderSystem;

use App\Form\Type\APIKeyType;
use App\Settings\SettingsIcon;
use Jbtronics\SettingsBundle\Metadata\EnvVarMode;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Symfony\Component\Translation\TranslatableMessage as TM;

#[Settings(label: new TM("settings.ips.ettinger"), description: new TM("settings.ips.ettinger.help"))]
#[SettingsIcon("fa-plug")]
class EttingerSettings
{
    #[SettingsParameter(label: new TM("settings.ips.ettinger.apiKey"), description: new TM("settings.ips.ettinger.apiKey.help"),
        formType: APIKeyType::class,
        formOptions: ["help_html" => true]
    )]
    public ?string $apiKey = null;
    
    #[SettingsParameter(label: new TM("settings.ips.ettinger.enabled"))]
    public bool $enabled = false;
    
    #[SettingsParameter(label: new TM("settings.ips.ettinger.searchLimit"),
        description: new TM("settings.ips.ettinger.searchLimit.help"),
        formType: NumberType::class, formOptions: ["attr" => ["min" => 1, "max" => 100]]
    )]
    #[Assert\Range(min: 1, max: 100)]
    public int $searchLimit = 10;
    
    #[SettingsParameter(label: new TM("settings.ips.ettinger.language"),
        formType: LocaleSelectType::class,
        formOptions: ["preferred_choices" => ["en", "de"]],
    )]
    #[Assert\LocaleSelectType()]
    public string $locale = "en";
    
    #[SettingsParameter(label: new TM("settings.ips.ettinger.category"),
        formType: Category::class,
    )]
    #[Assert\Category()]
    public string $category = "";
}