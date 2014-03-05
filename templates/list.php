<div ng-hide="artists" id="emptystate">
  <span ng-hide="loading" translate>Nothing in here. Upload your music!</span>
  <span ng-show="loading" translate>Loading ...</span>
</div>

<div class="navbar navbar-default navbar-fixed-top interpret">
  <div class="row">
    <div class="col-xs-4">
      <a class="btn btn-default navbar-btn btn-info left-block" href="../.." target="_self">
        <img alt="{{'Previous' | translate }}"
              src="<?php p(OCP\image_path('music', 'new/angle_left.svg')) ?>" />
        Home
      </a>
    </div>
    <div class="col-xs-4 text-center">
      <div class="navbar-text">Artists</div>
    </div>
    <div class="col-xs-4">
      <a class="btn btn-default navbar-btn btn-info playing-btn right-block" ng-click="showPlayer()" ng-show="currentTrack">
        <img alt="{{'Previous' | translate }}"
              src="<?php p(OCP\image_path('music', 'new/angle_right.svg')) ?>" />
        <span> Now <br/> 
          Playing
        </span>  
      </a>
    </div>
  </div>
</div>

<div ng-show="filter == 'artist'" class="list-group interpret-list">
  <a ng-repeat="artist in artists" 
    ng-click="artistClicked(artist)" 
    class="list-group-item">
    <span class='left'>{{artist.name}}</span>
  </a>
</div>

<div ng-show="filter == 'album'" class="list-group interpret-list">
  <a ng-repeat="album in albums" 
    ng-click="albumClicked(album)" 
    class="list-group-item">
    <span class='left'>{{album.name}}</span>
  </a>
</div>

<div ng-show="filter == 'track'" class="list-group interpret-list">
  <a ng-repeat="track in tracks" 
    ng-click="trackClicked(track, tracks)" 
    class="list-group-item">
    <span class='left'>{{track.title}}</span>
  </a>
</div>

<div class="navbar navbar-default navbar-fixed-bottom interpret">
  <div class="row">
    <div class="col-xs-4 text-center">
        <a class="btn btn-default navbar-btn btn-info toggle-btn" ng-click="artistFilterClicked()" ng-class="(filter == 'artist') ? 'active' : '">
          Artists
        </a>
    </div>
    <div class="col-xs-4 text-center">
        <a class="btn btn-default navbar-btn btn-info toggle-btn" ng-click="albumFilterClicked()" ng-class="(filter == 'album') ? 'active' : ''">
          Albums
        </a>
    </div>
    <div class="col-xs-4 text-center">
        <a class="btn btn-default navbar-btn btn-info toggle-btn" ng-click="trackFilterClicked()" ng-class="(filter == 'track') ? 'active' : ''">
          Tracks 
        </a>
    </div>
  </div>
</div>