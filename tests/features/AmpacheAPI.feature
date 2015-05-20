Feature: ls
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
      | SimonBowman            | 8      | 70    |
      | Simon Bowman           | 1      | 8     |

  Scenario: List all albums
    Given I am logged in with an auth token
    When I request the "albums" resource
    Then I should get:
      | name                                                | artist                 | tracks | year |
      | The Butcher s Ballroom                              | Diablo Swing Orchestra | 13     | 2009 |
      | NUANCE II                                           | Pascalb                | 10     | 2008 |
      | Grace Original Film Score                           | SimonBowman            | 5      | 2013 |
      | Backwards Original Film Score                       | SimonBowman            | 14     | 2013 |
      | NDP Philo Cafe Original Film S                      | SimonBowman            | 12     | 2014 |
      | The Visitor Original Film Scor                      | SimonBowman            | 5      | 2013 |
      | Nuance                                              | Pascalb                | 8      | 2006 |
      | The Crucible Original Theatre                       | SimonBowman            | 8      | 2014 |
      | Francesco da Milano 1497 1543                       | SimonBowman            | 6      | 2013 |
      | Instrumental Film Music Vol 1                       | SimonBowman            | 10     | 2013 |
      | Orchestral Film Music Vol 1                         | SimonBowman            | 10     | 2013 |
      | Witness for the Prosecution: Original Theatre Score | Simon Bowman           | 8      | 2014 |

