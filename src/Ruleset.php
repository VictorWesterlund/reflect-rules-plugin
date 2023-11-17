<?php

	namespace ReflectRules;

	// Use the Response class from Reflect to override endpoint processing if requested
	use \Reflect\Response;

	use \ReflectRules\Rules;

	require_once "../vendor/autoload.php";

	require_once "Rules.php";

	class Ruleset {
		// This plugin will return exit with a Reflect\Response if errors are found
		private bool $exit_on_errors;

		// Array of RuleError instances
		private array $errors = [];

		public function __construct(bool $exit_on_errors = true) {
			// Set exit on errors override flag
			$this->exit_on_errors = $exit_on_errors;
		}

		// Return property names for all Rules in array
		private static function get_property_names(array $rules): array {
			return array_map(fn(Rules $rule): string => $rule->get_property_name(), $rules);
		}

		// ----

		// Append an error to the array of errors
		private function add_error(string $name, string $message): self {
			// Create sub array for name if it doesn't exist
			if (!array_key_exists($name, $this->errors)) {
				$this->errors[$name] = [];
			}

			$this->errors[$name][] = $message;
			return $this;
		}

		private function eval_property_name_diff($rules, $scope_keys): void {
			// Get property names that aren't defiend in the ruleset
			$invalid_properties = array_diff($scope_keys, self::get_property_names($rules));

			// Add error for each invalid property name
			foreach ($invalid_properties as $invalid_property) {
				$this->add_error($invalid_property, "Unknown property name '{$invalid_property}'");
			}
		}

		// Evaluate Rules against a given value
		private function eval_rules(Rules $rules, array &$scope): void {
			$name = $rules->get_property_name();

			// Check if property name exists in scope
			if (!$rules->eval_required($scope)) {
				// Set property name value on superglobal to null if not defined
				$scope[$name] = null;

				// Don't perform further processing if the property is optional and not provided
				if (!$rules->required) {
					return;
				}

				$this->add_error($name, "Value can not be empty");
			}

			$value = $scope[$name];

			/*
				Eval each rule that has been set.
				The error messages will be returned 
			*/

			if ($rules->type && !$rules->eval_type($value)) {
				$this->add_error($name, "Value must be of type '{$rules->type->name}'");
			}

			if ($rules->min && !$rules->eval_min($value)) {
				$this->add_error($name, "Value must be larger or equal to {$rules->min}");
			}

			if ($rules->max && !$rules->eval_max($value)) {
				$this->add_error($name, "Value must be smaller or equal to {$rules->max}");
			}
		}

		// Evaluate all Rules in this Ruleset against values in scope and return true if no errors were found
		private function eval_all_rules(array $rules, array &$scope): bool {
			foreach ($rules as $rule) {
				$this->eval_rules($rule, $scope);
			}

			return empty($this->errors);
		}

		// ----

		// Perform request processing on GET properties (search parameters)
		public function GET(array $rules): true|array|Response {
			$this->eval_property_name_diff($rules, array_keys($_GET));

			$is_valid = $this->eval_all_rules($rules, $_GET);

			// Return errors as a Reflect\Response
			if (!$is_valid && $this->exit_on_errors) {
				return new Response($this->errors, 422);
			}

			return $is_valid ? true : $this->errors;
		}

		// Perform request processing on POST properties (request body)
		public function POST(array $rules): true|array|Response {
			$this->eval_property_name_diff($rules, array_keys($_POST));

			$is_valid = $this->eval_all_rules($rules, $_POST);

			// Return errors as a Reflect\Response
			if (!$is_valid && $this->exit_on_errors) {
				return new Response($this->errors, 422);
			}

			return $is_valid ? true : $this->errors;
		}
	}