/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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

import "tom-select/dist/css/tom-select.bootstrap5.css";
import '../../css/components/tom-select_extensions.css';
import TomSelect from "tom-select";
import {Controller} from "@hotwired/stimulus";


export default class extends Controller {
    _tomSelect;

    _emptyMessage;

    connect() {

        //Extract empty message from data attribute
        this._emptyMessage = this.element.getAttribute("data-empty-message") ?? "";

        const allowAdd = this.element.getAttribute("data-allow-add") === "true";
        const addHint = this.element.getAttribute("data-add-hint") ?? "";

        let settings = {
            allowEmptyOption: true,
            selectOnTab: true,
            maxOptions: null,
            create: allowAdd,
            createFilter: /\D/, //Must contain a non-digit character, otherwise they would be recognized as DB ID

            searchField: [
                {field: "text", weight : 2},
                {field: "parent", weight : 0.5},
                {field: "path", weight : 1.0},
            ],

            render: {
                item: this.renderItem.bind(this),
                option: this.renderOption.bind(this),
                option_create: function(data, escape) {
                    return '<div class="create"><i class="fa-solid fa-plus fa-fw"></i>&nbsp;<strong>' + escape(data.input) + '</strong>&hellip;&nbsp;' +
                        '<small class="text-muted float-end">(' + addHint +')</small>' +
                        '</div>';
                },
            }
        };

        this._tomSelect = new TomSelect(this.element, settings);
    }

    getTomSelect() {
        return this._tomSelect;
    }

    renderItem(data, escape) {
        //Render empty option as full row
        if (data.value === "") {
            if (this._emptyMessage) {
                return '<div class="tom-select-empty-option"><span class="text-muted"><b>' + escape(this._emptyMessage) + '</b></span></div>';
            } else {
                return '<div>&nbsp;</div>';
            }
        }

        if (data.short) {
            return '<div><b>' + escape(data.short) + '</b></div>';
        }

        let name = "";
        if (data.parent) {
            name += escape(data.parent) + "&nbsp;→&nbsp;";
        }
        name += "<b>" + escape(data.text) + "</b>";

        return '<div>' + (data.image ? "<img class='structural-entity-select-image' style='margin-right: 5px;' ' src='" + data.image + "'/>" : "") + name + '</div>';
    }

    renderOption(data, escape) {
        //Render empty option as full row
        if (data.value === "") {
            if (this._emptyMessage) {
                return '<div class="tom-select-empty-option"><span class="text-muted">' + escape(this._emptyMessage) + '</span></div>';
            } else {
                return '<div>&nbsp;</div>';
            }
        }


        //Indent the option according to the level
        let level_html = '&nbsp;&nbsp;&nbsp;'.repeat(data.level);

        let filter_badge = "";
        if (data.filetype_filter) {
            filter_badge = '<span class="badge bg-warning float-end"><i class="fa-solid fa-file-circle-exclamation"></i>&nbsp;' + escape(data.filetype_filter) + '</span>';
        }

        let symbol_badge = "";
        if (data.symbol) {
            symbol_badge = '<span class="badge bg-primary ms-2">' + escape(data.symbol) + '</span>';
        }

        let parent_badge = "";
        if (data.parent) {
            parent_badge = '<span class="ms-3 badge rounded-pill bg-secondary float-end picker-us"><i class="fa-solid fa-folder-tree"></i>&nbsp;' + escape(data.parent) + '</span>';
        }

        let image = "";
        if (data.image) {
            image = '<img class="structural-entity-select-image" style="margin-left: 5px;" src="' + data.image + '"/>';
        }

        return '<div>' + level_html + escape(data.text) + image + symbol_badge + parent_badge + filter_badge + '</div>';
    }

}
