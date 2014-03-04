<?php
\OCP\Util::addScript('music', 'vendor/angular/angular.min');
\OCP\Util::addScript('music', 'vendor/angular-route/angular-route.min');
\OCP\Util::addScript('music', 'vendor/angular-animate/angular-animate.min');
\OCP\Util::addScript('music', 'vendor/angular-touch/angular-touch.min');
\OCP\Util::addScript('music', 'vendor/underscore/underscore.min');
\OCP\Util::addScript('music', 'vendor/soundmanager/soundmanager2');
\OCP\Util::addScript('music', 'vendor/restangular/restangular.min');
\OCP\Util::addScript('music', 'vendor/angular-gettext/angular-gettext.min');
\OCP\Util::addScript('music', 'public/app');

\OCP\Util::addStyle('music', 'style-playerbar');
\OCP\Util::addStyle('music', 'app');
?>

<div id="app" ng-app="Music" ng-cloak ng-init="started = false; lang = '<?php p($_['lang']) ?>'">

	<div ng-controller="MainController">

		<script type="text/ng-template" id="list.html">
			<?php print_unescaped($this->inc('list')) ?>
		</script>

		<script type="text/ng-template" id="artist-detail.html">
			<?php print_unescaped($this->inc('artist-detail')) ?>
		</script>

		<script type="text/ng-template" id="playing.html">
			<?php print_unescaped($this->inc('playing')) ?>
		</script>

		<div id="playerbar" ng-if="started === started" ng-controller="PlayerController" ng-class="{started: started}">
		</div>

		<div id="app-content" class='{{animationType}}' ng-view ng-class="{started: started}"></div>

		<div class="navbar navbar-default navbar-fixed-bottom interpret">
	    <div class="row">
	      <div class="col-xs-4">
	          <a class="btn btn-default navbar-btn btn-info" href="../files" ng-click="switchAnimationType('animation-goes-right')">
	            &lsaquo; home 
	          </a>
	      </div>
	      <div class="col-xs-8">
	          <p class="navbar-text push-left">Interpreten</p>
	      </div>
	    </div>
	  </div>
	</div>

</div>
