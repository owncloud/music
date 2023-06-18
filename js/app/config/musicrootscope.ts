/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2023
 */

import * as ng from 'angular';

/**
 * Extension of the AngularJS IRootScopeService with some app-specific fields
 */
export interface MusicRootScope extends ng.IRootScopeService {
    currentView : string;
    playing : boolean;
	playingView : string;
    loading : boolean;
    loadingCollection : boolean;
}