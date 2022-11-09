Feature: Subsonic API - Inernet Radio
  In order to listen to radio streams
  As a user
  I need to be able to create, get, update, and delete radio stations


  Scenario: Empty list of radio stations
    When I request the "getInternetRadioStations" resource
    Then the XML result should contain 0 "internetRadioStation" entries


  Scenario: Create a radio station
    When I specify the parameter "name" with value "My station"
    And I specify the parameter "streamUrl" with value "https://invalid.com/stream.mp3"
    And I specify the parameter "homepageUrl" with value "https://invalid.com/home"
    And I request the "createInternetRadioStation" resource
    Then I should get empty XML response


  Scenario: Create another radio station
    When I specify the parameter "name" with value "My another station"
    And I specify the parameter "streamUrl" with value "https://invalid.com/stream2.mp3"
    And I request the "createInternetRadioStation" resource
    Then I should get empty XML response


  Scenario: Get radio stations
    When I request the "getInternetRadioStations" resource
    Then I should get XML with "internetRadioStation" entries:
      | name               | streamUrl                       | homePageUrl              |
      | My station         | https://invalid.com/stream.mp3  | https://invalid.com/home |
      | My another station | https://invalid.com/stream2.mp3 |                          |


  Scenario: Update station
    Given I request the "getInternetRadioStations" resource
    And I store the attribute "id" from the first "internetRadioStation" XML element
    When I specify the parameter "id" with the stored value
    And I specify the parameter "name" with value "My renamed station"
    And I specify the parameter "streamUrl" with value "https://invalid.com/new_stream.ogg"
    And I specify the parameter "homepageUrl" with value "https://invalid.com/sweet_home"
    And I request the "updateInternetRadioStation" resource
    Then I should get empty XML response


  Scenario: Get radio stations after the update
    When I request the "getInternetRadioStations" resource
    Then I should get XML with "internetRadioStation" entries:
      | name               | streamUrl                          | homePageUrl                    |
      | My renamed station | https://invalid.com/new_stream.ogg | https://invalid.com/sweet_home |
      | My another station | https://invalid.com/stream2.mp3    |                                |


  Scenario: Delete the first station
    Given I request the "getInternetRadioStations" resource
    And I store the attribute "id" from the first "internetRadioStation" XML element
    When I specify the parameter "id" with the stored value
    And I request the "deleteInternetRadioStation" resource
    Then I should get empty XML response


  Scenario: Get remaining radio station after deleting one
    When I request the "getInternetRadioStations" resource
    Then I should get XML with "internetRadioStation" entries:
      | name               | streamUrl                          | homePageUrl                    |
      | My another station | https://invalid.com/stream2.mp3    |                                |


  Scenario: Delete the last station
    Given I request the "getInternetRadioStations" resource
    And I store the attribute "id" from the first "internetRadioStation" XML element
    When I specify the parameter "id" with the stored value
    And I request the "deleteInternetRadioStation" resource
    Then I should get empty XML response
