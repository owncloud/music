Feature: Subsonic API - Search
  In order to browse my music collection
  As a user
  I need to be able to search artists, albums, and songs

  Scenario: Query matches one artist
    When I specify the parameter "query" with value "simon"
    And I request the "search2" resource
    Then the XML result should contain "artist" entries:
      | name            |
      | Simon Bowman    |
    And the XML result should contain 0 "album" entries
    And the XML result should contain 0 "song" entries

  Scenario: Query matches two albums
    When I specify the parameter "query" with value "music vol"
    And I request the "search2" resource
    Then the XML result should contain "album" entries:
      | title                           | artist        |
      | Instrumental Film Music Vol. 1  | Simon Bowman  |
      | Orchestral Film Music Vol. 1    | Simon Bowman  |
    And the XML result should contain 0 "artist" entries
    And the XML result should contain 0 "song" entries

  Scenario: Query finds entries of all types
    When I specify the parameter "query" with value "an"
    And I request the "search2" resource
    Then the XML result should contain "artist" entries:
      | name            |
      | Simon Bowman    |
    And the XML result should contain "album" entries:
      | title           | artist                    |
      | Nuance          | Pascal Boiseau (Pascalb)  |
    And the XML result should contain "song" entries:
      | title           | album                     | artist                    | duration  | year      | track     |
      | Gunpowder Chant | The Butcher's Ballroom    | Diablo Swing Orchestra    | 1         | 2009      | 7         |
      | Médiane         | Nuance                    | Pascal Boiseau (Pascalb)  | 3         | 2006      | 1         |

  Scenario: Query matches entries of all types, but only albums requested
    When I specify the parameter "query" with value "an"
    And I specify the parameter "artistCount" with value "0"
    And I specify the parameter "songCount" with value "0"
    And I request the "search2" resource
    Then the XML result should contain "album" entries:
      | title           | artist                    |
      | Nuance          | Pascal Boiseau (Pascalb)  |
    And the XML result should contain 0 "artist" entries
    And the XML result should contain 0 "song" entries

  Scenario: Query matches a song and results are requested as JSON
    When I specify the parameter "query" with value "pitbull"
    And I request the "search2" resource in JSON
    Then I should get JSON with "song" entries:
      | title                      | album                  | artist                 | duration | year | track |
      | Poetic Pitbull Revolutions | The Butcher's Ballroom | Diablo Swing Orchestra | 2        | 2009 | 3     |
