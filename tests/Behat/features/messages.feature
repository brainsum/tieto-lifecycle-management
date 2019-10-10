@api
@javascript
@life-cycle-management
Feature:
  Tieto Life-cycle Management message display.

  Background:
    Given I am logged in as a user with the "content_manager" role

  # @todo: Check the form message location.
  Scenario Outline: Node create message.
    Given I visit the "<page>" page
    Then I should see the text "<message>"

    Examples:
      | page              | message                                                                                                                                      |
      | Profiled news add | News items will be assigned automatic unpublish and deletion dates. These dates can be overridden by entering respective dates manually.     |
      | Tieto news add    | News items will be assigned automatic unpublish and deletion dates. These dates can be overridden by entering respective dates manually.     |
      | News link add     | News items will be assigned automatic unpublish and deletion dates. These dates can be overridden by entering respective dates manually.     |
      | Service alert add | Service alerts will be assigned automatic unpublish and deletion dates. These dates can be overridden by entering respective dates manually. |

  Scenario Outline: Unmoderated, unpublished node edit message.
    Given I edit a manually not moderated "<state>" content of type "<content type>"
    Then I should see the "<message type>" moderation message "<message>"

    Examples:
      | state     | content type  | message type | message                                                                                                                                                                                                                           |
      | Draft     | Service alert | delete       | Service alerts will be assigned automatic unpublish and deletion dates. These dates can be overridden by entering respective dates manually and by publishing the content. Otherwise, this content will be deleted on @deleteDate |
      | Draft     | Profiled news | delete       | News items will be assigned automatic unpublish and deletion dates. These dates can be overridden by entering respective dates manually and by publishing the content. Otherwise, this content will be deleted on @deleteDate     |
      | Published | Service alert | unpublish    | Service alerts will be assigned automatic unpublish and deletion dates. This content will be unpublished on @unpublishDate                                                                                                        |
      | Published | Profiled news | unpublish    | News items will be assigned automatic unpublish and deletion dates. These dates can be overridden by entering respective dates manually or by re-publishing the content. This article will be unpublished on @unpublishDate       |

  Scenario Outline: Unmoderated, once published node edit message.
    Given a manually not moderated "<state>" "<content type>", last published "<time>" ago
    Then I should see the "<message type>" moderation message "<message>"

    Examples:
      | state       | content type  | time     | message type | message                                                                                                                                                                                                                     |
      # 3 months
      | Published   | Service alert | -93 days | unpublish    | Service alerts will be assigned automatic unpublish and deletion dates. This content will be unpublished on @unpublishDate                                                                                                  |
      | Published   | Profiled news | -93 days | unpublish    | News items will be assigned automatic unpublish and deletion dates. These dates can be overridden by entering respective dates manually or by re-publishing the content. This article will be unpublished on @unpublishDate |
      | Unpublished | Service alert | -93 days | archive      | Service alerts will be assigned automatic unpublish and deletion dates. This content will be archived on @archiveDate                                                                                                       |
      | Unpublished | Profiled news | -93 days | archive      | News items will be assigned automatic unpublish and deletion dates. These dates can be overridden by entering respective dates manually or by re-publishing the content. This article will be archived on @archiveDate      |
      | Archived    | Service alert | -93 days | delete       | Service alerts will be assigned automatic unpublish and deletion dates. This content will be deleted on @deleteDate                                                                                                         |
      | Archived    | Profiled news | -93 days | delete       | News items will be assigned automatic unpublish and deletion dates. These dates can be overridden by entering respective dates manually or by re-publishing the content. This article will be deleted on @deleteDate        |

  Scenario Outline: Moderated, once published node edit message.
    Given a manually moderated "<state>" "<content type>", last published "<time>" ago
    Then I should not see the "<message type>" moderation message "<message>"

    Examples:
      | state       | content type  | time     | message type | message                                                                                                                                                                                                                     |
      # 3 months
      | Published   | Service alert | -93 days | unpublish    | Service alerts will be assigned automatic unpublish and deletion dates. This content will be unpublished on @unpublishDate                                                                                                  |
      | Published   | Profiled news | -93 days | unpublish    | News items will be assigned automatic unpublish and deletion dates. These dates can be overridden by entering respective dates manually or by re-publishing the content. This article will be unpublished on @unpublishDate |
      | Unpublished | Service alert | -93 days | archive      | Service alerts will be assigned automatic unpublish and deletion dates. This content will be archived on @archiveDate                                                                                                       |
      | Unpublished | Profiled news | -93 days | archive      | News items will be assigned automatic unpublish and deletion dates. These dates can be overridden by entering respective dates manually or by re-publishing the content. This article will be archived on @archiveDate      |
      | Archived    | Service alert | -93 days | delete       | Service alerts will be assigned automatic unpublish and deletion dates. This content will be deleted on @deleteDate                                                                                                         |
      | Archived    | Profiled news | -93 days | delete       | News items will be assigned automatic unpublish and deletion dates. These dates can be overridden by entering respective dates manually or by re-publishing the content. This article will be deleted on @deleteDate        |

#  Scenario Outline: Moderated, unpublished node edit message.
#    Given I edit a manually moderated "<state>" content of type "<content type>"
#    Then I should not see the "<message type>" moderation message "<message>"
#
#    # @todo: These will fail as no date is calculated for them. Figure out how to work around that, or remove these.
#
#    Examples:
#      | state       | content type  | message type | message                                                                                                                                                                                                                     |
#      | Unpublished | Service alert | archive      | Service alerts will be assigned automatic unpublish and deletion dates. This content will be archived on @archiveDate                                                                                                       |
#      | Unpublished | Profiled news | archive      | News items will be assigned automatic unpublish and deletion dates. These dates can be overridden by entering respective dates manually or by re-publishing the content. This article will be archived on @archiveDate      |
#      | Archived    | Service alert | delete       | Service alerts will be assigned automatic unpublish and deletion dates. This content will be deleted on @deleteDate                                                                                                         |
#      | Archived    | Profiled news | delete       | News items will be assigned automatic unpublish and deletion dates. These dates can be overridden by entering respective dates manually or by re-publishing the content. This article will be deleted on @deleteDate        |
