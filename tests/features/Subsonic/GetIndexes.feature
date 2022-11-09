Feature: Subsonic API - Get indexes
  In order to browse my music collection
  As a user
  I need to be able to list all artists in my collection


  Scenario: Get artists
    When I specify the parameter "musicFolderId" with value "artists"
    And I request the "getIndexes" resource
    Then I should get XML with "index" entries:
      | name |
      | D    |
      | P    |
      | S    |
    And the XML result should contain "index/artist" entries:
      | name                     |
      | Diablo Swing Orchestra   |
      | Pascal Boiseau (Pascalb) |
      | Simon Bowman             |
