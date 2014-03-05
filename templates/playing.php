  <div class="navbar navbar-default navbar-fixed-top interpret">
    <div class="row">
      <div class="col-xs-2">
          <a class="btn btn-default navbar-btn btn-info" ng-click="showArtists()">
            <img alt="{{'Previous' | translate }}" src="<?php p(OCP\image_path('music', 'new/angle_left.svg')) ?>" />
          </a>
      </div>
      <div class="col-xs-8 text-center">
          <p class="navbar-text">Now Playing</p>
      </div>
    </div>
  </div>

  <div class="row">    
    <img src="http://placehold.it/500x500" class="img-responsive playing-cover center-block img-thumbnail"  alt="album art" />
    <span>
      <p class="playinginfo text-center"><strong>{{currentTrack.artist.name}}</strong></p>
      <p class="playinginfo text-center">{{currentTrack.title}}</p>
      <p class="playinginfo text-center">{{position}} / {{duration}}</p>
      
    </span>
  </div>




  <div class="progress">
    <div class="progress-bar" role="progressbar" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100" style="width: {{(position/duration)*100}}%;">
      
      <span class="sr-only">{{(position/duration)*100}}% Complete</span>
    </div>
  </div>

  <div class="navbar navbar-default navbar-fixed-bottom interpret">
   <div class="row">
      <div class="col-xs-2 text-center">
        <img alt="backward icon" class="playing-btn" 
              src="<?php p(OCP\image_path('music', 'new/backward.svg')) ?>"
              ng-click="prev()" />
      </div>
      <div class="col-xs-2 text-center" ng-if="!playing">
        <img alt="play icon" class="playing-btn"
              src="<?php p(OCP\image_path('music', 'new/play.svg')) ?>"
              ng-click="toggle()" />
      </div>
      <div class="col-xs-2 text-center" ng-if="playing">
        <img alt="pause icon" class="playing-btn"
              src="<?php p(OCP\image_path('music', 'new/pause.svg')) ?>"
              ng-click="toggle()" />
      </div>
      <div class="col-xs-2 text-center">
        <img alt="forward icon" class="playing-btn"
              src="<?php p(OCP\image_path('music', 'new/forward.svg')) ?>"
              ng-click="next()" />
      </div>
      <div class="col-xs-2 col-xs-offset-2 text-center">
        <img alt="random icon" class="toggle-btn"
              src="<?php p(OCP\image_path('music', 'new/random.svg')) ?>"
              ng-click="shuffle=!shuffle"
              ng-class="{active: shuffle}" />
      </div>
      <div class="col-xs-2 text-center">
        <img alt="repeat icon" class="toggle-btn"
              src="<?php p(OCP\image_path('music', 'new/repeat.svg')) ?>"
              ng-click="repeat=!repeat"
              ng-class="{active: repeat}" />
      </div>
  </div>