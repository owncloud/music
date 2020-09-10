Feature: Ampache API - Albums
  In order to browse my music collection
  As a user
  I need to be able to list my albums with or without filter

  Scenario: List all albums
    Given I am logged in with an auth token
    When I request the "albums" resource
    Then I should get:
      | name                                                | artist                   | tracks | year |
      | Instrumental Film Music Vol. 1                      | Simon Bowman             | 2      | 2013 |
      | Nuance                                              | Pascal Boiseau (Pascalb) | 3      | 2006 |
      | Orchestral Film Music Vol. 1                        | Simon Bowman             | 3      | 2013 |
      | The Butcher's Ballroom                              | Diablo Swing Orchestra   | 5      | 2009 |

  Scenario: List filtered albums
    Given I am logged in with an auth token
    When I specify the parameter "filter" with value "Nuance"
    And I request the "albums" resource
    Then I should get:
      | name                                                | artist                   | tracks | year |
      | Nuance                                              | Pascal Boiseau (Pascalb) | 3      | 2006 |

  Scenario: List exact filtered albums
    Given I am logged in with an auth token
    When I specify the parameter "filter" with value "Nuan"
    And I specify the parameter "exact" with value "true"
    And I request the "albums" resource
    Then I should get:
      | name                                                | artist                 | tracks | year |
