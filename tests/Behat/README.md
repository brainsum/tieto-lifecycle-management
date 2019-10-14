# Life-cycle management / Behat tests

## Requirements
### Packages
The `brainsum/drupal-behat-testing` is used for most of the boilerplate code.
  
### Behat config

The `Behat\MinkExtension` and `Drupal\DrupalExtension` are required.
Optionally, if you'd like to use DI for the project-specific context,
take a look at `Zalas\Behat\NoExtension` or `FriendsOfBehat\ContextServiceExtension`.

## Setup
- Initialize a new or use an existing behat suite for your project, e.g in `{project-root}/tests/behat`.
- Add a new suite, e.g `life-cycle-management`
- Create features files based on `tieto_lifecycle_management/tests/src/Behat/features`
    - The supplied features are for reference only, you need to adjust dates, content types, roles, etc. to the needs of your site
- (Optional) For modularity, extend traits used by the BaseContext
    - These can be found in the `drupal-behat-testing` package, under the `Brainsum\DrupalBehatTesting` namespace
- Create a new context
    - Extend `Brainsum\tieto_lifecycle_management\Tests\Behat\Context\BaseContext`
    - Implement the abstract functions that are needed
    - Suggestion: You should set up autoloading
        - E.g: `{project-root}/tests/behat/src` folder as `MyProject\Tests\Behat`
        - Then you can add your new context to `MyProject\Tests\Behat\Context` and use that in the `behat.yml` file
