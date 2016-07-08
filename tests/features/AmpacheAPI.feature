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
      | Pascalb / Pascal Boiseau | 2      | 5     |
      | SimonBowman              | 2      | 5     |
      | Unknown Artist           | 5      | 0     |

  Scenario: List filtered artists
    Given I am logged in with an auth token
    When I specify the parameter "filter" with value "Pascalb"
    And I request the "artists" resource
    Then I should get:
      | name                     | albums | songs |
      | Pascalb / Pascal Boiseau | 2      | 5     |

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
      | Instrumental Film Music Vol 1                       | Unknown Artist         | 2      | 2013 |
      | Nuance                                              | Unknown Artist         | 3      | 2006 |
      | NUANCE II                                           | Unknown Artist         | 2      | 2008 |
      | Orchestral Film Music Vol 1                         | Unknown Artist         | 3      | 2013 |
      | The Butcher s Ballroom                              | Unknown Artist         | 5      | 2009 |

  Scenario: List filtered albums
    Given I am logged in with an auth token
    When I specify the parameter "filter" with value "Nuance"
    And I request the "albums" resource
    Then I should get:
      | name                                                | artist                 | tracks | year |
      | Nuance                                              | Unknown Artist         | 3      | 2006 |
      | NUANCE II                                           | Unknown Artist         | 2      | 2008 |

  Scenario: List exact filtered albums
    Given I am logged in with an auth token
    When I specify the parameter "filter" with value "Nuance"
    And I specify the parameter "exact" with value "true"
    And I request the "albums" resource
    Then I should get:
      | name                                                | artist                 | tracks | year |
      | Nuance                                              | Unknown Artist         | 3      | 2006 |

  Scenario: List 10 songs
    Given I am logged in with an auth token
    When I specify the parameter "limit" with value "10"
    And I request the "songs" resource
    Then I should get:
      | title                          | artist      | album                             | time | track |
      | Aç                             | Pascalb / Pascal Boiseau | Nuance                            | 187  | 7     |
      | Balrog Boogie                  | Diablo Swing Orchestra | The Butcher s Ballroom | 234  | 1     |
      | Forgotten Days                 | SimonBowman | Instrumental Film Music Vol 1     | 195  | 1     |
      | FX Love                        | Pascalb / Pascal Boiseau | NUANCE II                         | 229  | 1     |
      | Gunpowder Chant                | Diablo Swing Orchestra | The Butcher s Ballroom | 111  | 7     |
      | Heroines                       | Diablo Swing Orchestra | The Butcher s Ballroom | 322  | 2     |
      | Médiane                        | Pascalb / Pascal Boiseau | Nuance                            | 203  | 1     |
      | Nocturne                       | SimonBowman | Instrumental Film Music Vol 1     | 142  | 2     |
      | Poetic Pitbull Revolutions     | Diablo Swing Orchestra | The Butcher s Ballroom | 288  | 3     |
      | Rag Doll Physics               | Diablo Swing Orchestra | The Butcher s Ballroom | 233  | 4     |

  Scenario: List songs that contain "an"
    Given I am logged in with an auth token
    When I specify the parameter "filter" with value "an"
    And I request the "songs" resource
    Then I should get:
      | title                            | artist                 | album                          | time | track |
      | Gunpowder Chant                  | Diablo Swing Orchestra | The Butcher s Ballroom         | 111  | 7     |
      | Médiane                          | Pascalb / Pascal Boiseau | Nuance                         | 203  | 1     |

  Scenario: List songs that contain "Mediane"
    Given I am logged in with an auth token
    When I specify the parameter "filter" with value "Médiane"
    And I specify the parameter "exact" with value "true"
    And I request the "songs" resource
    Then I should get:
      | title                            | artist                   | album                          | time | track |
      | Médiane                          | Pascalb / Pascal Boiseau | Nuance                         | 203  | 1     |
