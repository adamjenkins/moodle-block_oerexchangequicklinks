@block @block_oerexchangequicklinks @javascript
Feature: Add the OER Exchange quick links block to the Dashboard
  In order to quickly re-open resources I have tried
  As a user
  I need to be able to add the block to my Dashboard

  Scenario: An admin adds the block to their Dashboard and it renders correctly
    Given I log in as "admin"
    And I visit "/my/"
    And I turn editing mode on
    When I add the "OER Exchange: quick links" block
    Then I should see "Resources you try from the OER Exchange will appear here for quick access." in the "OER Exchange: quick links" "block"

  Scenario: An ordinary user can add the block to their own Dashboard
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And I log in as "student1"
    And I visit "/my/"
    And I turn editing mode on
    When I add the "OER Exchange: quick links" block
    Then I should see "Resources you try from the OER Exchange will appear here for quick access." in the "OER Exchange: quick links" "block"
