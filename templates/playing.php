  <div class="navbar navbar-default navbar-fixed-top interpret">
    <div class="row">
      <div class="col-xs-4">
          <a class="btn btn-default navbar-btn btn-info" ng-click="showArtists()">
            <img alt="{{'Previous' | translate }}"
                src="<?php p(OCP\image_path('music', 'new/angle_left.svg')) ?>" />
          </a>
      </div>
      <div class="col-xs-8">
          <p class="navbar-text">Now Playing</p>
      </div>
    </div>
  </div>

  <div class="row">    
    <img src="http://placehold.it/500x500" class="img-responsive playing-cover center-block img-thumbnail"  alt="album art" />
    <span>
      <p class="playinginfo text-center"><strong>Interpret</strong></p>
      <p class="playinginfo text-center">Track</p>
    </span>
  </div>




  <div class="progress">
    <div class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 60%;">
      <span class="sr-only">60% Complete</span>
    </div>
  </div>

  <div class="navbar navbar-default navbar-fixed-bottom interpret">
   <div class="row playingtools">
      <div class="col-xs-2 text-center">
        <img alt="backward icon"
              src="<?php p(OCP\image_path('music', 'new/backward.svg')) ?>"
              ng-click="prev()" />
      </div>
      <div class="col-xs-2 text-center">
        <img alt="play icon"
              src="<?php p(OCP\image_path('music', 'new/play.svg')) ?>"
              ng-click="toggle()" />
      </div>
      <div class="col-xs-2 text-center">
        <img alt="forward icon"
              src="<?php p(OCP\image_path('music', 'new/forward.svg')) ?>"
              ng-click="next()" />
      </div>
      <div class="col-xs-2 col-xs-offset-2 text-center">
        <img alt="random icon"
              src="<?php p(OCP\image_path('music', 'new/random.svg')) ?>"
              ng-click="shuffle=!shuffle"
              ng-class="{active: shuffle}" />
      </div>
      <div class="col-xs-2 text-center">
        <img alt="repeat icon"
              src="<?php p(OCP\image_path('music', 'new/repeat.svg')) ?>"
              ng-click="repeat=!repeat"
              ng-class="{active: repeat}" />
      </div>
  </div>