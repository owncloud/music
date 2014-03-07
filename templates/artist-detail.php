<div>

  <div class="navbar navbar-default navbar-fixed-top interpret">
    <!--div class="row">
      <div class="col-xs-4"-->
          <a class="btn btn-default navbar-btn btn-info left-btn" ng-click="showArtists()">
            <span class="glyphicon glyphicon-chevron-left"></span>
            <span class="btn-label">
              Artists
            </span>
          </a>
      <!--/div>
      <div class="col-xs-4 col-xs-offset-4">
        <!--a class="btn btn-default navbar-btn btn-info playing-btn right-block" ng-click="showPlayer()" ng-show="currentTrack">
          <img alt="{{'Previous' | translate }}"
                src="<?php p(OCP\image_path('music', 'new/angle_right.svg')) ?>" />
          <span> Now <br/> 
            Playing
          </span>  
        </a-->


        <a class="btn btn-default navbar-btn btn-info playing-btn right-btn" ng-click="showPlayer()" ng-show="currentTrack">
          <span class="btn-label"> 
            Now <br/> 
            Playing
          </span>
          <span class="glyphicon glyphicon-chevron-right"></span>
        </a>
    <!--/div-->
    </div>
  </div>


	<div ng-repeat="album in activeArtist.albums | orderBy:'year'">
		<ul class="list-group interpret-detail-list">
			<li class="list-group-item list-group-item-info" title="{{ album.name }} ({{ album.year}})">
				<p class="list-group-item-heading">
					{{ album.name }} 
				</p>
			</li>

			<li class="list-group-item" ng-repeat="track in album.tracks" ng-click="trackClicked(track, album.tracks)"> 
				<span>
					{{ track.number }}. {{ track.title }}
				</span>
			</li>
		</ul> 
	</div>

	<div class="navbar navbar-default navbar-fixed-bottom interpret">
		<div class="row">
			<div class="col-xs-12 text-center">
				<p class="navbar-text text-center">{{ activeArtist.name }}</p>
			</div>
		</div>
	</div>
</div>