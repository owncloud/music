  <div class="navbar navbar-default navbar-fixed-top interpret">
    <div class="row">
      <div class="col-xs-4">
          <a class="btn btn-default navbar-btn btn-info" href="../files" ng-click="switchAnimationType('animation-goes-right')">
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
    <img src="http://placehold.it/500x500" class="img-responsive playing-cover center-block"  alt="album art" />
  </div>



  <div class="navbar navbar-default navbar-fixed-bottom interpret">
   <div class="row playerbar">
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