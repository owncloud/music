Feature: Ampache API
  In order to browse my music collection
  As a user
  I need to be able to list my music files

  Scenario: List all artists
    Given I am logged in with an auth token
    When I request the "artists" resource
    Then I should get:
      | name                     | albums | songs |
      | Diablo Swing Orchestra   | 1      | 5     |
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

  Scenario: List 10 songs
    Given I am logged in with an auth token
    When I specify the parameter "limit" with value "10"
    And I request the "songs" resource
    Then I should get:
      | title                          | artist                   | album                          | time | track |
      | Aç                             | Pascal Boiseau (Pascalb) | Nuance                         | 3    | 7     |
      | Balrog Boogie                  | Diablo Swing Orchestra   | The Butcher's Ballroom         | 3    | 1     |
      | Forgotten Days                 | Simon Bowman             | Instrumental Film Music Vol. 1 | 3    | 1     |
      | Gunpowder Chant                | Diablo Swing Orchestra   | The Butcher's Ballroom         | 1    | 7     |
      | Heroines                       | Diablo Swing Orchestra   | The Butcher's Ballroom         | 3    | 2     |
      | Médiane                        | Pascal Boiseau (Pascalb) | Nuance                         | 3    | 1     |
      | Nocturne                       | Simon Bowman             | Instrumental Film Music Vol. 1 | 2    | 2     |
      | Poetic Pitbull Revolutions     | Diablo Swing Orchestra   | The Butcher's Ballroom         | 2    | 3     |
      | Rag Doll Physics               | Diablo Swing Orchestra   | The Butcher's Ballroom         | 3    | 4     |
      | Reverie                        | Simon Bowman             | Orchestral Film Music Vol. 1   | 2    | 9     |

  Scenario: List songs that contain "an"
    Given I am logged in with an auth token
    When I specify the parameter "filter" with value "an"
    And I request the "songs" resource
    Then I should get:
      | title                          | artist                   | album                          | time | track |
      | Gunpowder Chant                | Diablo Swing Orchestra   | The Butcher's Ballroom         | 1    | 7     |
      | Médiane                        | Pascal Boiseau (Pascalb) | Nuance                         | 3    | 1     |

  Scenario: List songs that contain "Mediane"
    Given I am logged in with an auth token
    When I specify the parameter "filter" with value "Médiane"
    And I specify the parameter "exact" with value "true"
    And I request the "songs" resource
    Then I should get:
      | title                          | artist                   | album                          | time | track |
      | Médiane                        | Pascal Boiseau (Pascalb) | Nuance                         | 3    | 1     |
