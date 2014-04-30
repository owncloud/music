<form>

	<input type="text"
		ng-model="plistName"
		placeholder="<?php p($l->t('Name')); ?>"
		name="name"
		autofocus>
	<button title="<?php p($l->t('Add')); ?>"
			class="primary"
			ng-disabled="!plistName.trim()"
			ng-click="addPlaylist(plistName)"><?php p($l->t('Add')); ?></button>
</form>
<!--

    <form name="myForm">
    <div class="control-group" ng-class="{error: myForm.name.$invalid && !myForm.name.$pristine}">
    <label>Name</label>
    <input type="text" name="name" ng-model="project.name" required>
    <span ng-show="myForm.name.$error.required && !myForm.name.$pristine" class="help-inline">
    Required {{myForm.name.$pristine}}</span>
    </div>
     
    <div class="control-group" ng-class="{error: myForm.site.$invalid && !myForm.site.$pristine}">
    <label>Website</label>
    <input type="url" name="site" ng-model="project.site" required>
    <span ng-show="myForm.site.$error.required && !myForm.name.$pristine" class="help-inline">
    Required</span>
    <span ng-show="myForm.site.$error.url" class="help-inline">
    Not a URL</span>
    </div>
     
    <label>Description</label>
    <textarea name="description" ng-model="project.description"></textarea>
     
    <br>
    <a href="#/" class="btn">Cancel</a>
    <button ng-click="save()" ng-disabled="myForm.$invalid"
    class="btn btn-primary">Save</button>
    <button ng-click="destroy()"
    ng-show="project.$remove" class="btn btn-danger">Delete</button>
    </form>
-->
