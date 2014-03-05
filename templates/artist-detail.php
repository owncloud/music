<div>
  <div class="navbar navbar-default navbar-fixed-top interpret">
    <div class="row">
      <div class="col-xs-4">
          <a class="btn btn-default navbar-btn btn-info" ng-click="showArtists()">
            &lsaquo; Interpreten
          </a>
      </div>
      <div class="col-xs-8">
          <p class="navbar-text">{{artist.name}}</p>
      </div>
    </div>
  </div>

  <div ng-repeat="album in artist.albums | orderBy:'year'">
    <ul class="list-group interpret-detail-list">
      <li class="list-group-item list-group-item-info" ng-click="playAlbum(album)" title="{{ album.name }} ({{ album.year}})">
        <strong class="list-group-item-heading">{{ album.name }}</strong>
      </li>
      <li class="list-group-item" ng-repeat="track in album.tracks" ng-click="trackClicked(track, album.tracks)"> 
        <span>
          {{track.number}}. {{ track.title }}
        </span>
      </li>
    </ul> 
  </div>

  <div class="navbar navbar-default navbar-fixed-bottom interpret">
    <div class="row">
      <div class="col-xs-4">
      </div>
      <div class="col-xs-8">
      </div>
    </div>
  </div>
</div>