Feature: Subsonic API - Podcasts
  In order to enjoy podcasts
  As a user
  I need to be able subscribe, list, and unsubscribe podcast channels


  Scenario: Empty list of podcasts
    When I request the "getPodcasts" resource
    Then the XML result should contain 0 "channel" entries


  Scenario: Empty list of newest podcast episodes
    When I request the "getNewestPodcasts" resource
    Then the XML result should contain 0 "episode" entries


  Scenario: Subscribe a channel
    When I specify the parameter "url" with value "https://github.com/paulijar/music/files/7227952/890.txt"
    And I request the "createPodcastChannel" resource
    Then I should get empty XML response


  Scenario: Subscribe another channel
    When I specify the parameter "url" with value "https://github.com/paulijar/music/files/7227999/ostanasuntoja.txt"
    And I request the "createPodcastChannel" resource
    Then I should get empty XML response


  Scenario: Subscribe a third channel
    When I specify the parameter "url" with value "https://github.com/paulijar/music/files/7228005/rss.txt"
    And I request the "createPodcastChannel" resource
    Then I should get empty XML response


  Scenario: Get the podcasts
    When I request the "getPodcasts" resource with parameter "includeEpisodes" having value "false"
    Then I should get XML with "channel" entries:
      | title                                | url                                                               |
      | Made You Think                       | https://github.com/paulijar/music/files/7228005/rss.txt           |
      | Ostan Asuntoja Podcast               | https://github.com/paulijar/music/files/7227999/ostanasuntoja.txt |
      | Pirjo Heikkilä lukee vanhoja kirjoja | https://github.com/paulijar/music/files/7227952/890.txt           |
    And the XML result should contain 0 "channel/episode" entries


  Scenario: Get the default number of newest episodes
    When I request the "getNewestPodcasts" resource
    Then the XML result should contain 20 "episode" entries


  Scenario: Get large number of newest episodes
    When I specify the parameter "count" with value "153"
    And I request the "getNewestPodcasts" resource
    Then the XML result should contain 153 "episode" entries


  Scenario: Get a few newest episodes
    When I specify the parameter "count" with value "3"
    And I request the "getNewestPodcasts" resource
    Then I should get XML with "episode" entries:
    | title                                                                              | track | publishDate              | contentType | suffix | size      | duration | bitRate |
    | Making the Navalmanack: Interview with Eric Jorgenson                              | 66    | 2020-12-16T16:58:21.000Z | audio/mpeg  | mp3    | 60919192  | 3801     | 128     |
    | Seek Wealth, Not Money or Status. The Almanack of Naval Ravikant by Eric Jorgenson | 65    | 2020-10-23T19:29:03.000Z | audio/mpeg  | mp3    | 79074141  | 4936     | 128     |
    | The Universal Currency: Energy and Civilization by Vaclav Smil                     | 64    | 2020-10-09T17:25:31.000Z | audio/mpeg  | mp3    | 106127516 | 6627     | 128     |


  Scenario: Get a channel with episodes
    Given I request the "getPodcasts" resource with parameter "includeEpisodes" having value "false"
    And I store the attribute "id" from the first "channel" XML element
    When I specify the parameter "id" with the stored value
    And I request the "getPodcasts" resource with parameter "includeEpisodes" having value "true"
    Then I should get XML with "channel" entries:
      | title                                | url                                                               |
      | Made You Think                       | https://github.com/paulijar/music/files/7228005/rss.txt           |
    And the XML result should contain 67 "channel/episode" entries


  Scenario: Delete the first channel
    Given I request the "getPodcasts" resource with parameter "includeEpisodes" having value "false"
    And I store the attribute "id" from the first "channel" XML element
    When I specify the parameter "id" with the stored value
    And I request the "deletePodcastChannel" resource
    Then I should get empty XML response


  Scenario: Delete the second channel
    Given I request the "getPodcasts" resource with parameter "includeEpisodes" having value "false"
    And I store the attribute "id" from the first "channel" XML element
    When I specify the parameter "id" with the stored value
    And I request the "deletePodcastChannel" resource
    Then I should get empty XML response


  Scenario: Get the last remaining podcast channel after deleting two
    When I request the "getPodcasts" resource with parameter "includeEpisodes" having value "false"
    Then I should get XML with "channel" entries:
      | title                                | url                                                               |
      | Pirjo Heikkilä lukee vanhoja kirjoja | https://github.com/paulijar/music/files/7227952/890.txt           |


  Scenario: Delete the last channel
    Given I request the "getPodcasts" resource with parameter "includeEpisodes" having value "false"
    And I store the attribute "id" from the first "channel" XML element
    When I specify the parameter "id" with the stored value
    And I request the "deletePodcastChannel" resource
    Then I should get empty XML response
