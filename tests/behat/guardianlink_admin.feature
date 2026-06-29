@tool @tool_guardianlink
Feature: GuardianLink administration pages are reachable
  In order to administer delegated guardian/parent access
  As an administrator
  I need the GuardianLink admin pages and help manual to load

  Background:
    Given I log in as "admin"

  Scenario: The relationship registry page loads
    When I navigate to "Plugins > Admin tools > GuardianLink > Relationship registry" in site administration
    Then I should see "Relationship registry"

  Scenario: The help manual lists the documented pages
    When I navigate to "Plugins > Admin tools > GuardianLink > Help manual" in site administration
    Then I should see "GuardianLink help manual"
    And I should see "Relationship registry"
    And I should see "Privacy guarantees"

  Scenario: The help manual serves authorised adults and explains its inclusive language
    When I navigate to "Plugins > Admin tools > GuardianLink > Help manual" in site administration
    Then I should see "Words we use"
    And I should see "For authorised adults: your learner support dashboard"
    And I should see "Higher education: student-authorised supporters"

  Scenario: The consolidated report page loads
    When I navigate to "Plugins > Admin tools > GuardianLink > Oversight reports" in site administration
    Then I should see "Consolidated figures"
