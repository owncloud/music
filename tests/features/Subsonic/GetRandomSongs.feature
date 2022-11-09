Feature: Subsonic API - Get random songs
  In order to browse my music collection
  As a user
  I need to be able to list some or all of my songs in radom order


  Scenario: Get one random song
    When I specify the parameter "size" with value "1"
    And I request the "getRandomSongs" resource
    Then I should get XML containing 1 "song" entry


  Scenario: Get default number of random songs
    When I request the "getRandomSongs" resource
    Then I should get XML containing 10 "song" entries


  Scenario: Get all songs in random order
    When I specify the parameter "size" with value "1000"
    And I request the "getRandomSongs" resource
    Then I should get XML containing 13 "song" entries
