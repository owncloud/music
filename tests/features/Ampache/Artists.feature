Feature: Ampache API - Artists
  In order to browse my music collection
  As a user
  I need to be able to list my artists with or without filter


  Scenario: List all artists
    Given I am logged in with an auth token
    When I request the "artists" resource
    Then I should get:
      | name                     | albums | songs |
      | Diablo Swing Orchestra   | 1      | 5     |
      | Pascal Boiseau (Pascalb) | 1      | 3     |
      | Simon Bowman             | 2      | 5     |


  Scenario: List 2 artists with offset and large limit
    Given I am logged in with an auth token
    When I specify the parameter "offset" with value "1"
    And I specify the parameter "limit" with value "1000"
    And I request the "artists" resource
    Then I should get:
      | name                     | albums | songs |
      | Pascal Boiseau (Pascalb) | 1      | 3     |
      | Simon Bowman             | 2      | 5     |


  Scenario: List filtered artists
    Given I am logged in with an auth token
    When I specify the parameter "filter" with value "Simon Bowman"
    And I request the "artists" resource
    Then I should get:
      | name                     | albums | songs |
      | Simon Bowman             | 2      | 5     |


  Scenario: List exact filtered artists
    Given I am logged in with an auth token
    When I specify the parameter "filter" with value "Simon"
    And I specify the parameter "exact" with value "true"
    And I request the "artists" resource
    Then I should get:
      | name                   | albums | songs |
