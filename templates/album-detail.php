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

  <ul class="list-group interpret-detail-list">

    <li class="list-group-item" ng-repeat="track in activeAlbum.tracks" ng-click="trackClicked(track, album.tracks)"> 
      <span>
        {{track.number}}. {{ track.title }}
      </span>
    </li>
  </ul> 


<div class="navbar navbar-default navbar-fixed-bottom interpret">
  <div class="row">
    <div class="col-xs-4">
        {{activeAlbum.name}}
    </div>
    <div class="col-xs-4 text-center">
        {{activeAlbum.artists.0.name}}
    </div>
    <div class="col-xs-4 text-right">
        {{activeAlbum.year}}
    </div>
  </div>
</div>