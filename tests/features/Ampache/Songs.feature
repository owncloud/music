Feature: Ampache API - Songs
  In order to browse my music collection
  As a user
  I need to be able to list my songs with or without filter


  Scenario: List 10 songs
    Given I am logged in with an auth token
    When I specify the parameter "limit" with value "10"
    And I request the "songs" resource
    Then I should get:
      | title                          | artist                   | album                          | time | track |
      | Aç                             | Pascal Boiseau (Pascalb) | Nuance                         | 3    | 7     |
      | Balrog Boogie                  | Diablo Swing Orchestra   | The Butcher's Ballroom         | 2    | 1     |
      | Forgotten Days                 | Simon Bowman             | Instrumental Film Music Vol. 1 | 2    | 1     |
      | Gunpowder Chant                | Diablo Swing Orchestra   | The Butcher's Ballroom         | 1    | 7     |
      | Heroines                       | Diablo Swing Orchestra   | The Butcher's Ballroom         | 2    | 2     |
      | Médiane                        | Pascal Boiseau (Pascalb) | Nuance                         | 2    | 1     |
      | Nocturne                       | Simon Bowman             | Instrumental Film Music Vol. 1 | 2    | 2     |
      | Poetic Pitbull Revolutions     | Diablo Swing Orchestra   | The Butcher's Ballroom         | 2    | 3     |
      | Rag Doll Physics               | Diablo Swing Orchestra   | The Butcher's Ballroom         | 3    | 4     |
      | Reverie                        | Simon Bowman             | Orchestral Film Music Vol. 1   | 1    | 9     |


  Scenario: List 3 songs with offset
    Given I am logged in with an auth token
    When I specify the parameter "limit" with value "3"
    And I specify the parameter "offset" with value "6"
    And I request the "songs" resource
    Then I should get:
      | title                          | artist                   | album                          | time | track |
      | Nocturne                       | Simon Bowman             | Instrumental Film Music Vol. 1 | 2    | 2     |
      | Poetic Pitbull Revolutions     | Diablo Swing Orchestra   | The Butcher's Ballroom         | 2    | 3     |
      | Rag Doll Physics               | Diablo Swing Orchestra   | The Butcher's Ballroom         | 3    | 4     |


  Scenario: List songs that contain "an"
    Given I am logged in with an auth token
    When I specify the parameter "filter" with value "an"
    And I request the "songs" resource
    Then I should get:
      | title                          | artist                   | album                          | time | track |
      | Gunpowder Chant                | Diablo Swing Orchestra   | The Butcher's Ballroom         | 1    | 7     |
      | Médiane                        | Pascal Boiseau (Pascalb) | Nuance                         | 2    | 1     |


  Scenario: List songs that contain "an" with offset and limit
    Given I am logged in with an auth token
    When I specify the parameter "filter" with value "an"
    And I specify the parameter "limit" with value "2"
    And I specify the parameter "offset" with value "1"
    And I request the "songs" resource
    Then I should get:
      | title                          | artist                   | album                          | time | track |
      | Médiane                        | Pascal Boiseau (Pascalb) | Nuance                         | 2    | 1     |


  Scenario: List songs that contain exactly "Médiane"
    Given I am logged in with an auth token
    When I specify the parameter "filter" with value "Médiane"
    And I specify the parameter "exact" with value "true"
    And I request the "songs" resource
    Then I should get:
      | title                          | artist                   | album                          | time | track |
      | Médiane                        | Pascal Boiseau (Pascalb) | Nuance                         | 2    | 1     |
