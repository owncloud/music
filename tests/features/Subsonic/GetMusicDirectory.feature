Feature: Subsonic API - Get music directory
  In order to browse my music collection
  As a user
  I need to be able to list albums of an artist and songs of an album


  Scenario: Get albums of an artist
    Given I specify the parameter "query" with value "Simon Bowman"
    And I request the "search2" resource
    And I store the attribute "id" from the first "artist" XML element
    When I specify the parameter "id" with the stored value
    And I request the "getMusicDirectory" resource
    Then I should get XML with "child" entries:
      | title                          | artist       |
      | Instrumental Film Music Vol. 1 | Simon Bowman |
      | Orchestral Film Music Vol. 1   | Simon Bowman |


  Scenario: Get songs of an album
    Given I specify the parameter "query" with value "Nuance"
    And I request the "search2" resource
    And I store the attribute "id" from the first "album" XML element
    When I specify the parameter "id" with the stored value
    And I request the "getMusicDirectory" resource
    Then I should get XML with "child" entries:
      | title   | album  | artist                    | duration | year | track |
      | Médiane | Nuance | Pascal Boiseau (Pascalb)  | 2        | 2006 | 1     |
      | Aç      | Nuance | Pascal Boiseau (Pascalb)  | 3        | 2006 | 7     |
      | Vagues  | Nuance | Pascal Boiseau (Pascalb)  | 3        | 2006 | 8     |
