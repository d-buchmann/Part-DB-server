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

import {Controller} from "@hotwired/stimulus";

/**
 * Remembers the next free position on a partly used label sheet: saves it when the labels are downloaded,
 * and "New sheet" starts again at the first position.
 */
export default class extends Controller
{
    static values = {url: String, token: String, sheet: String, position: Number};
    static targets = ['skip'];

    save() {
        const body = new FormData();
        body.append('_token', this.tokenValue);
        body.append('sheet', this.sheetValue);
        body.append('position', String(this.positionValue));
        fetch(this.urlValue, {method: 'POST', body: body});
    }

    newSheet() {
        this.skipTarget.value = 0;
        //Regenerate the preview (the form's first submit button is "update")
        this.skipTarget.form.requestSubmit();
    }
}
