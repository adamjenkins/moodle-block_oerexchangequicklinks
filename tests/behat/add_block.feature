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
