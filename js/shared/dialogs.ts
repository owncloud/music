/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2023 - 2025
 */

OCA.Music = OCA.Music || {};

/** @namespace
 * 
 * Wrapper for dialogs provided by the host cloud. Provide a bit more concise interface
 * and hide any differences between the supported cloud versions.
 */
OCA.Music.Dialogs = class {

	static filePicker(title : string, callback : CallableFunction, mimetype : string|string[], path : string|undefined = undefined) {
		// The filepicker interface wants to get the initial path without a trailing slash
		if (path?.endsWith('/')) {
			path = path.slice(0, -1);
		}

		OC.dialogs.filepicker(
			title,
			(datapath : string, _returnType : any) => callback(datapath), // arg _returnType is passed by NC but not by OC
			false, // multiselect
			mimetype,
			true, // modal
			OC.dialogs.FILEPICKER_TYPE_CHOOSE, // type (only on NC)
			path // initial folder, only on NC16+
		);
	}

	static folderPicker(title : string, callback : CallableFunction, path = '') {
		OCA.Music.Dialogs.filePicker(
			title,
			(selectedPath : string) => {
				if (!selectedPath.endsWith('/')) {
					selectedPath = selectedPath + '/';
				}
				callback(selectedPath);
			},
			'httpd/unix-directory',
			path // initial folder, only on NC16+
		);
	}

	static prompt(title : string, text : string, callback : CallableFunction, defaultValue : string = '', allowEmpty : boolean = false) {
		// The class name of the dialog differs/ between NC/OC. Also, OC leaks the dialogs and leaves
		// the old dialogs in the DOM tree as hidden; hence target only visible dialogs.
		const getInputEl = () => $('.dialog__content:visible input')[0] ?? $('.oc-dialog:visible input')[0];

		OC.dialogs.prompt(
			text,
			title,
			(accept : boolean, _value : string) => {
				// the value from the original callback doesn't get set if the user selected the default without typing anything
				let realValue = $(getInputEl()).val() as string;
				realValue = realValue.trim();
				if (accept && !allowEmpty && realValue === '') {
					this.prompt(title, text, callback, defaultValue, allowEmpty);
				} else {
					callback(accept, realValue);
				}
			},
			true, // modal
			title, // name
			false // password
		);

		// Setting the default value requires major hacking because the spawning is an asynchronous operation,
		// involving loading a new JS file at least on newer NC versions.
		OCA.Music.Utils.executeOnceRefAvailable(
			getInputEl, 
			(inputElem : HTMLInputElement) => $(inputElem).val(defaultValue),
			100
		);
	}
};