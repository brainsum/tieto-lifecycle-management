@api
@javascript
@life-cycle-management
Feature:
  Tieto Life-cycle Management automated moderation.

  Background:
    Given I am logged in as a user with the "content_manager" role

  Scenario Outline: Node automation.
    Given a manually not moderated "<original state>" "<content type>", last published "<time>" ago
    When I run cron
    Then the moderation state of the content should change to "<target state>"

    # Use days for relative times so tests are more stable.
    Examples:
      | original state | target state | content type  | time              |
      # 1 month
      | Published      | Unpublished  | Service alert | 31 days 1 minute  |
      # 7 months
      | Published      | Unpublished  | News link     | 217 days 1 minute |
      # 2 years
      | Unpublished    | Archived     | Service alert | 732 days 1 minute  |
      # 2 years
      | Unpublished    | Archived     | News link     | 732 days 1 minute  |

  Scenario Outline: Draft removal.
    Given a manually not moderated, never published "<original state>" "<content type>", updated "<time>" ago
    When I run cron
    Then the content should be deleted

    Examples:
      | original state | content type  | time            |
      # 1 year
      | Draft          | News link     | 366 days 1 minute |
      # 1 year
      | Draft          | Service alert | 366 days 1 minute |

  Scenario Outline: Old content removal.
    Given a manually not moderated "<original state>" "<content type>", last published "<time>" ago
    When I run cron
    Then the content should be deleted
    Examples:
      | original state | content type  | time             |
      # 3 years
      | Archived       | News link     | 1098 days 1 minute |
      # 3 years
      | Archived       | Service alert | 1098 days 1 minute |

