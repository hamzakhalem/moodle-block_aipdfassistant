@block @block_aipdfassistant
Feature: Students can see the AI PDF Assistant in a course
  In order to study from PDF files
  As a student
  I need to see the assistant block when it is configured

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: Teacher adds the block and students see a notice while it is not configured
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "AI PDF Assistant" block
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "The assistant is not available yet"

  Scenario: Students see the chat once an AI provider is configured
    Given the following config values are set as admin:
      | provider | custom                 | block_aipdfassistant |
      | baseurl  | https://llm.example/v1 | block_aipdfassistant |
      | model    | test-model             | block_aipdfassistant |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "AI PDF Assistant" block
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "Drop a PDF here"
    And I should see "Summarize"
    And I should see "Explain"
