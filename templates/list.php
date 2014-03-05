<div ng-hide="artists" id="emptystate">
  <span ng-hide="loading" translate>Nothing in here. Upload your music!</span>
  <span ng-show="loading" translate>Loading ...</span>
</div>

<div class="navbar navbar-default navbar-fixed-top interpret">
  <div class="row">
    <div class="col-xs-4">
      <a class="btn btn-default navbar-btn btn-info left-block" ng-click="showOwncloud()">
        <img alt="{{'Previous' | translate }}"
              src="<?php p(OCP\image_path('music', 'new/angle_left.svg')) ?>" />
        Home
      </a>
    </div>
    <div class="col-xs-4">
      <p class="navbar-text text-center">Interprets</p>
    </div>
    <div class="col-xs-4">
      <a class="btn btn-default navbar-btn btn-info playing-btn right-block" ng-click="showPlayer()">
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
    <span class='left'>{{track.id}} - {{track.title}}</span>
  </a>
</div>

<div class="navbar navbar-default navbar-fixed-bottom interpret">
  <div class="row">
    <div class="col-xs-4">
        <a class="btn btn-default navbar-btn btn-info" ng-click="artistFilterClicked()">
          Interprets
        </a>
    </div>
    <div class="col-xs-4 text-center">
        <a class="btn btn-default navbar-btn btn-info" ng-click="albumFilterClicked()">
          Albums
        </a>
    </div>
    <div class="col-xs-4 text-right">
        <a class="btn btn-default navbar-btn btn-info" ng-click="trackFilterClicked()">
          Tracks 
        </a>
    </div>
  </div>
</div>