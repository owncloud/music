Feature: Ampache API
  In order to browse my music collection
  As a user
  I need to be able to list my music files

  Scenario: List all artists
    Given I am logged in with an auth token
    When I request the "artists" resource
    Then I should get:
      | name                   | albums | songs |
      | Diablo Swing Orchestra | 1      | 13    |
      | Pascalb                | 2      | 18    |
      | Simon Bowman           | 1      | 8     |
      | SimonBowman            | 8      | 70    |

  Scenario: List filtered artists
    Given I am logged in with an auth token
    When I specify the parameter "filter" with value "Simon Bowman"
    And I request the "artists" resource
    Then I should get:
      | name                   | albums | songs |
      | Simon Bowman           | 1      | 8     |

  Scenario: List exact filtered artists
    Given I am logged in with an auth token
    When I specify the parameter "filter" with value "Pascal"
    And I specify the parameter "exact" with value "true"
    And I request the "artists" resource
    Then I should get:
      | name                   | albums | songs |

  Scenario: List all albums
    Given I am logged in with an auth token
    When I request the "albums" resource
    Then I should get:
      | name                                                | artist                 | tracks | year |
      | Backwards Original Film Score                       | SimonBowman            | 14     | 2013 |
      | Francesco da Milano 1497 1543                       | SimonBowman            | 6      | 2013 |
      | Grace Original Film Score                           | SimonBowman            | 5      | 2013 |
      | Instrumental Film Music Vol 1                       | SimonBowman            | 10     | 2013 |
      | NDP Philo Cafe Original Film S                      | SimonBowman            | 12     | 2014 |
      | NUANCE II                                           | Pascalb                | 10     | 2008 |
      | Nuance                                              | Pascalb                | 8      | 2006 |
      | Orchestral Film Music Vol 1                         | SimonBowman            | 10     | 2013 |
      | The Butcher s Ballroom                              | Diablo Swing Orchestra | 13     | 2009 |
      | The Crucible Original Theatre                       | SimonBowman            | 8      | 2014 |
      | The Visitor Original Film Scor                      | SimonBowman            | 5      | 2013 |
      | Witness for the Prosecution: Original Theatre Score | Simon Bowman           | 8      | 2014 |

  Scenario: List filtered albums
    Given I am logged in with an auth token
    When I specify the parameter "filter" with value "Nuance"
    And I request the "albums" resource
    Then I should get:
      | name                                                | artist                 | tracks | year |
      | NUANCE II                                           | Pascalb                | 10     | 2008 |
      | Nuance                                              | Pascalb                | 8      | 2006 |

  Scenario: List exact filtered albums
    Given I am logged in with an auth token
    When I specify the parameter "filter" with value "Nuance"
    And I specify the parameter "exact" with value "true"
    And I request the "albums" resource
    Then I should get:
      | name                                                | artist                 | tracks | year |
      | Nuance                                              | Pascalb                | 8      | 2006 |

