Feature: Subsonic API - Playlists
  In order to utilize playlists
  As a user
  I need to be able to create, get, update, and delete playlists


  Scenario: Create empty playlist
    When I specify the parameter "name" with value "My playlist"
    And I request the "createPlaylist" resource
    Then I should get XML with "playlist" entries:
      | name                | songCount | public |
      | My playlist         | 0         | false  |


  Scenario: Create playlist with one entry
    Given I specify the parameter "query" with value "Heroines"
    And I request the "search2" resource
    And I store the attribute "id" from the first "song" XML element
    When I specify the parameter "songId" with the stored value of "id"
    And I specify the parameter "name" with value "My another playlist"
    And I request the "createPlaylist" resource
    Then I should get XML with "playlist/entry" entries:
      | title    | album                          | artist                 | duration  | year | track |
      | Heroines | The Butcher's Ballroom         | Diablo Swing Orchestra | 2         | 2009 | 2     |


  Scenario: Get playlists
    When I request the "getPlaylists" resource
    Then I should get XML with "playlist" entries:
      | name                | songCount | public |
      | My playlist         | 0         | false  |
      | My another playlist | 1         | false  |


  Scenario: Update playlist
    Given I specify the parameter "query" with value "Nocturne"
    And I request the "search2" resource
    And I store the attribute "id" from the first "song" XML element as "songIdToAdd"
    And I request the "getPlaylists" resource
    And I store the attribute "id" from the second "playlist" XML element
    When I specify the parameter "playlistId" with the stored value of "id"
    And I specify the parameter "name" with value "My renamed playlist"
    And I specify the parameter "songIdToAdd" with the stored value
    And I request the "updatePlaylist" resource
    Then I should get empty XML response


  Scenario: Get playlists after the update
    When I request the "getPlaylists" resource
    Then I should get XML with "playlist" entries:
      | name                | songCount | public |
      | My playlist         | 0         | false  |
      | My renamed playlist | 2         | false  |


  Scenario: Get playlist along with its songs
    Given I request the "getPlaylists" resource
    And I store the attribute "id" from the second "playlist" XML element
    When I specify the parameter "id" with the stored value
    And I request the "getPlaylist" resource
    Then I should get XML with "entry" entries:
      | title    | album                          | artist                 | duration  | year | track |
      | Heroines | The Butcher's Ballroom         | Diablo Swing Orchestra | 2         | 2009 | 2     |
      | Nocturne | Instrumental Film Music Vol. 1 | Simon Bowman           | 2         | 2013 | 2     |


  Scenario: Delete the first playlist
    Given I request the "getPlaylists" resource
    And I store the attribute "id" from the first "playlist" XML element
    When I specify the parameter "id" with the stored value
    And I request the "deletePlaylist" resource
    Then I should get empty XML response


  Scenario: Get remaining playlist after deleting one
    When I request the "getPlaylists" resource
    Then I should get XML with "playlist" entry:
      | name                | songCount | public |
      | My renamed playlist | 2         | false  |


  Scenario: Delete the last playlist
    Given I request the "getPlaylists" resource
    And I store the attribute "id" from the first "playlist" XML element
    When I specify the parameter "id" with the stored value
    And I request the "deletePlaylist" resource
    Then I should get empty XML response
