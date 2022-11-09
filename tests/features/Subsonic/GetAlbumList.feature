Feature: Subsonic API - Get album list
  In order to browse my music collection
  As a user
  I need to be able to list my music albums


  Scenario: List all albums
    When I specify the parameter "type" with value "alphabeticalByName"
    And I request the "getAlbumList" resource
    Then I should get XML with "album" entries:
      | title                                               | artist                   |
      | Instrumental Film Music Vol. 1                      | Simon Bowman             |
      | Nuance                                              | Pascal Boiseau (Pascalb) |
      | Orchestral Film Music Vol. 1                        | Simon Bowman             |
      | The Butcher's Ballroom                              | Diablo Swing Orchestra   |


  Scenario: List limited number of albums
    When I specify the parameter "type" with value "alphabeticalByName"
    And I specify the parameter "size" with value "2"
    And I request the "getAlbumList" resource
    Then I should get XML with "album" entries:
      | title                                               | artist                   |
      | Instrumental Film Music Vol. 1                      | Simon Bowman             |
      | Nuance                                              | Pascal Boiseau (Pascalb) |


  Scenario: List second page of albums
    When I specify the parameter "type" with value "alphabeticalByName"
    And I specify the parameter "size" with value "5"
    And I specify the parameter "offset" with value "2"
    And I request the "getAlbumList" resource
    Then I should get XML with "album" entries:
      | title                                               | artist                   |
      | Orchestral Film Music Vol. 1                        | Simon Bowman             |
      | The Butcher's Ballroom                              | Diablo Swing Orchestra   |


  Scenario: List all albums with the new API
    When I specify the parameter "type" with value "alphabeticalByName"
    And I request the "getAlbumList2" resource
    Then I should get XML with "album" entries:
      | name                                                | artist                   | songCount  |
      | Instrumental Film Music Vol. 1                      | Simon Bowman             | 2          |
      | Nuance                                              | Pascal Boiseau (Pascalb) | 3          |
      | Orchestral Film Music Vol. 1                        | Simon Bowman             | 3          |
      | The Butcher's Ballroom                              | Diablo Swing Orchestra   | 5          |


  Scenario: List all albums with the new API in JSON format
    When I specify the parameter "type" with value "alphabeticalByName"
    And I request the "getAlbumList2" resource in JSON
    Then I should get JSON with "album" entries:
      | name                                                | artist                   | songCount  |
      | Instrumental Film Music Vol. 1                      | Simon Bowman             | 2          |
      | Nuance                                              | Pascal Boiseau (Pascalb) | 3          |
      | Orchestral Film Music Vol. 1                        | Simon Bowman             | 3          |
      | The Butcher's Ballroom                              | Diablo Swing Orchestra   | 5          |


  Scenario: Get random albums
    When I specify the parameter "type" with value "random"
    And I specify the parameter "size" with value "3"
    And I request the "getAlbumList" resource
    Then I should get XML containing 3 "album" entries
