<div>
  <div class="navbar navbar-default navbar-fixed-top interpret">
    <div class="row">
      <div class="col-xs-4">
          <a class="btn btn-default navbar-btn btn-info" ng-click="showArtists()">
            <img alt="{{'Previous' | translate }}" src="<?php p(OCP\image_path('music', 'new/angle_left.svg')) ?>" />
            Interprets
          </a>
      </div>
      <div class="col-xs-4">
          <p class="navbar-text text-center">{{artist.name}}</p>
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

  <div ng-repeat="album in artist.albums | orderBy:'year'">
    <ul class="list-group interpret-detail-list">
      <li class="list-group-item list-group-item-info" title="{{ album.name }} ({{ album.year}})">
        <p class="list-group-item-heading">{{ album.name }}</p>
      </li>
      <li class="list-group-item" ng-repeat="track in album.tracks | orderBy:'number'" ng-click="playTrack(track)"> 
        <span class="track">
          {{track.number}}. {{ track.title }}
        </span>
      </li>
    </ul> 
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