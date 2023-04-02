Feature: Subsonic API - Starring
  In order to find my favorite songs, albums, and artists
  As a user
  I need to be able to star and unstar artists, albums, and songs, and list the starred entities


  Scenario: List starred entities while there are none
    When I request the "getStarred" resource
    Then the XML result should contain 0 "artist" entries
    And the XML result should contain 0 "album" entries
    And the XML result should contain 0 "song" entries


  Scenario: Star a single track
    Given I have stored "id" from the "song" matching "Heroines"
    When I specify the parameter "id" with the stored value
    And I request the "star" resource
    And I request the "getStarred" resource
    Then I should get XML with "song" entries:
      | title    | album                          | artist                 | duration  | year | track |
      | Heroines | The Butcher's Ballroom         | Diablo Swing Orchestra | 2         | 2009 | 2     |
    And the XML result should contain 0 "artist" entries
    And the XML result should contain 0 "album" entries


  Scenario: Star another track
    Given I have stored "id" from the "song" matching "Nocturne"
    When I specify the parameter "id" with the stored value
    And I request the "star" resource
    And I request the "getStarred" resource
    Then I should get XML with "song" entries:
      | title    | album                          | artist                 | duration  | year | track |
      | Heroines | The Butcher's Ballroom         | Diablo Swing Orchestra | 2         | 2009 | 2     |
      | Nocturne | Instrumental Film Music Vol. 1 | Simon Bowman           | 2         | 2013 | 2     |
    And the XML result should contain 0 "artist" entries
    And the XML result should contain 0 "album" entries


  Scenario: Star an album using the old API
    Given I have stored "id" from the "album" matching "The Butcher's Ballroom"
    When I specify the parameter "id" with the stored value
    And I request the "star" resource
    Then I should get empty XML response


  Scenario: Star an album using the new API
    Given I have stored "id" from the "album" matching "Orchestral Film Music Vol. 1"
    When I specify the parameter "albumId" with the stored value of "id"
    And I request the "star" resource
    Then I should get empty XML response


  Scenario: List starred entities while there are two starred tracks and two starred albums
    When I request the "getStarred" resource
    Then I should get XML with "album" entries:
      | title                          | artist                   |
      | Orchestral Film Music Vol. 1   | Simon Bowman             |
      | The Butcher's Ballroom         | Diablo Swing Orchestra   |
    And the XML result should contain 2 "song" entries
    And the XML result should contain 0 "artist" entries


  Scenario: Star an artist using the old API
    Given I have stored "id" from the "artist" matching "Simon Bowman"
    When I specify the parameter "id" with the stored value
    And I request the "star" resource
    Then I should get empty XML response


  Scenario: Star an artist using the new API
    Given I have stored "id" from the "artist" matching "Pascal Boiseau"
    When I specify the parameter "artistId" with the stored value of "id"
    And I request the "star" resource
    Then I should get empty XML response


  Scenario: List starred entities while there are two starred entities of each type
    When I request the "getStarred" resource
    Then the XML result should contain "artist" entries:
      | name                     |
      | Pascal Boiseau (Pascalb) |
      | Simon Bowman             |
    And the XML result should contain "album" entries:
      | title                        | artist                 |
      | Orchestral Film Music Vol. 1 | Simon Bowman           |
      | The Butcher's Ballroom       | Diablo Swing Orchestra |
    And the XML result should contain "song" entries:
      | title    | album                          | artist                 | duration  | year | track |
      | Heroines | The Butcher's Ballroom         | Diablo Swing Orchestra | 2         | 2009 | 2     |
      | Nocturne | Instrumental Film Music Vol. 1 | Simon Bowman           | 2         | 2013 | 2     |


  Scenario: List starred entities with getStarred2
    When I request the "getStarred2" resource
    Then the XML result should contain "artist" entries:
      | name                     |
      | Pascal Boiseau (Pascalb) |
      | Simon Bowman             |
    And the XML result should contain "album" entries:
      | name                         | artist                 | songCount |
      | Orchestral Film Music Vol. 1 | Simon Bowman           | 3         |
      | The Butcher's Ballroom       | Diablo Swing Orchestra | 5         |
    And the XML result should contain "song" entries:
      | title    | album                          | artist                 | duration  | year | track |
      | Heroines | The Butcher's Ballroom         | Diablo Swing Orchestra | 2         | 2009 | 2     |
      | Nocturne | Instrumental Film Music Vol. 1 | Simon Bowman           | 2         | 2013 | 2     |


  Scenario: List starred albums with getAlbumList
    When I specify the parameter "type" with value "starred"
    And I request the "getAlbumList" resource
    Then I should get XML with "album" entries:
      | title                        | artist                 |
      | Orchestral Film Music Vol. 1 | Simon Bowman           |
      | The Butcher's Ballroom       | Diablo Swing Orchestra |


  Scenario: Unstar a single track
    Given I have stored "id" from the "song" matching "Heroines"
    When I specify the parameter "id" with the stored value
    And I request the "unstar" resource
    And I request the "getStarred" resource
    Then I should get XML with "song" entries:
      | title    | album                          | artist                 | duration  | year | track |
      | Nocturne | Instrumental Film Music Vol. 1 | Simon Bowman           | 2         | 2013 | 2     |
    And the XML result should contain 2 "artist" entries
    And the XML result should contain 2 "album" entries


  Scenario: Unstar the last track
    Given I have stored "id" from the "song" matching "Nocturne"
    When I specify the parameter "id" with the stored value
    And I request the "unstar" resource
    And I request the "getStarred" resource
    Then the XML result should contain 0 "song" entries
    And the XML result should contain 2 "artist" entries
    And the XML result should contain 2 "album" entries


  Scenario: Unstar artist using the old API
    Given I have stored "id" from the "artist" matching "Simon Bowman"
    When I specify the parameter "id" with the stored value
    And I request the "unstar" resource
    And I request the "getStarred" resource
    Then the XML result should contain "artist" entries:
      | name                     |
      | Pascal Boiseau (Pascalb) |
    And the XML result should contain 0 "song" entries
    And the XML result should contain 2 "album" entries


  Scenario: Unstar artist using the new API
    Given I have stored "id" from the "artist" matching "Pascal Boiseau"
    When I specify the parameter "artistId" with the stored value of "id"
    And I request the "unstar" resource
    And I request the "getStarred" resource
    Then the XML result should contain 0 "artist" entries
    And the XML result should contain 0 "song" entries
    And the XML result should contain 2 "album" entries


  Scenario: Unstar album using the old API
    Given I have stored "id" from the "album" matching "Orchestral Film Music Vol. 1"
    When I specify the parameter "id" with the stored value
    And I request the "unstar" resource
    And I request the "getStarred" resource
    Then the XML result should contain "album" entries:
      | title                        | artist                 |
      | The Butcher's Ballroom       | Diablo Swing Orchestra |
    And the XML result should contain 0 "song" entries
    And the XML result should contain 0 "artist" entries


  Scenario: Unstar album using the new API
    Given I have stored "id" from the "album" matching "The Butcher's Ballroom"
    When I specify the parameter "albumId" with the stored value of "id"
    And I request the "unstar" resource
    And I request the "getStarred" resource
    Then the XML result should contain 0 "artist" entries
    And the XML result should contain 0 "song" entries
    And the XML result should contain 0 "album" entries
