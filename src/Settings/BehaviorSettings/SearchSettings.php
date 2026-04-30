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


namespace App\Settings\BehaviorSettings;

use App\Settings\SettingsIcon;
use Jbtronics\SettingsBundle\Metadata\EnvVarMode;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Symfony\Component\Translation\TranslatableMessage as TM;

#[Settings(name: "search", label: new TM("settings.behavior.search"))]
#[SettingsIcon('fa-search')]
class KeybindingsSettings
{
    /**
     * Whether to enable advanced search
     * @var bool
     */
    #[SettingsParameter(
        label: new TM("settings.behavior.search.enable_advanced_search"), 
        description: new TM("settings.behavior.search.enable_advanced_search.help"),
        envVar: "bool:ENABLE_ADVANCED_SEARCH", 
        envVarMode: EnvVarMode::OVERWRITE
    )]
    public bool $enableAdvancedSearch = true;
}
