<?php

	namespace ReflectRules;

	use \ReflectRules\Rules;

	require_once "Rules.php";

	// Available superglobal scopes
	enum Scope: string {
		case GET  = "_GET";
		case POST = "_POST";
	}

	enum Error {
		case UNKNOWN_PROPERTY_NAME;
		case MISSING_REQUIRED_PROPERTY;
		case INVALID_PROPERTY_TYPE;
		case VALUE_MIN_ERROR;
		case VALUE_MAX_ERROR;
	}

	class Ruleset {
		// Array of arrays with failed constraints
		private array $errors = [];

		// Aggregated rules for GET and POST
		private array $rules_get = [];
		private array $rules_post = [];

		public function __construct() {}

		// Return property names for all Rules in array
		private static function get_property_names(array $rules): array {
			return array_map(fn(Rules $rule): string => $rule->get_property_name(), $rules);
		}

		// ----

		// Append an error to the array of errors
		private function add_error(Error $error, Scope $scope, string $name, string $message): void {
			// Create sub array if this is the first error in this scope
			if (!array_key_exists($scope->name, $this->errors)) {
				$this->errors[$scope->name] = [];
			}

			// Create sub array if this is the first error for this property
			if (!array_key_exists($name, $this->errors[$scope->name])) {
				$this->errors[$scope->name][$name] = [];
			}

			$this->errors[$scope->name][$name][] = [
				"scope"         => $scope->name,
				"property_name" => $name,
				"error_code"    => $error->name,
				"error_message" => $message
			];
		}

		private function eval_property_name_diff(array $rules, Scope $scope): void {
			// Get array keys from superglobal
			$keys = array_keys($GLOBALS[$scope->value]);

			// Get property names that aren't defiend in the ruleset
			$invalid_names = array_diff($keys, self::get_property_names($rules));

			// Add error for each invalid property name
			foreach ($invalid_names as $invalid_name) {
				$this->add_error(Error::UNKNOWN_PROPERTY_NAME, $scope, $invalid_name, "Unknown property name '{$invalid_name}'");
			}
		}

		// Evaluate Rules against a given value
		private function eval_rules(Rules $rules, Scope $scope): void {
			// Get the name of the current property being evaluated
			$name = $rules->get_property_name();

			// Check if property name exists in scope
			if (!$rules->eval_required($scope)) {
				// Don't perform further processing if the property is optional and not provided
				if (!$rules->required) {
					return;
				}

				$this->add_error(Error::MISSING_REQUIRED_PROPERTY, $scope, $name, "Value can not be empty");
			}

			// Get value from scope for the current property
			$value = $GLOBALS[$scope->value][$name];

			/*
				Eval each rule that has been set.
				The error messages will be returned 
			*/

			if ($rules->types && !$rules->eval_type($value, $scope)) {
				// List names of each allowed type
				$types = implode(" or ", array_map(fn($type): string => $type->name, $rules->types));

				// List allowed enum values
				if ($rules->enum) {
					$values = implode(" or ", array_map(fn($value): string => "'{$value}'", $rules->enum));
					$this->add_error(Error::INVALID_PROPERTY_TYPE, $scope, $name, "Value must be exactly: {$values}");
				}

				$this->add_error(Error::INVALID_PROPERTY_TYPE, $scope, $name, "Value must be of type {$types}");
			}

			if ($rules->min && !$rules->eval_min($value, $scope)) {
				$this->add_error(Error::VALUE_MIN_ERROR, $scope, $name, "Value must be larger or equal to {$rules->min}");
			}

			if ($rules->max && !$rules->eval_max($value, $scope)) {
				$this->add_error(Error::VALUE_MAX_ERROR, $scope, $name, "Value must be smaller or equal to {$rules->max}");
			}
		}

		// Evaluate all Rules in this Ruleset against values in scope and return true if no errors were found
		private function eval_all_rules(array $rules, Scope $scope): bool {
			foreach ($rules as $rule) {
				$this->eval_rules($rule, $scope);
			}

			return empty($this->errors);
		}

		// ----

		// Perform request processing on GET properties (search parameters)
		public function GET(array $rules): void {
			array_merge($this->rules_get, $rules);
		}

		// Perform request processing on POST properties (request body)
		public function POST(array $rules): void {
			array_merge($this->rules_post, $rules);
		}

		// Evaluate all aggrgated rules
		public function eval(): true|array {
			$this->eval_property_name_diff($this->rules_get, Scope::GET);
			$this->eval_property_name_diff($this->rules_post, Scope::POST);

			$is_valid_get = $this->eval_all_rules($this->rules_get, Scope::GET);
			$is_valid_post = $this->eval_all_rules($this->rules_post, Scope::POST);

			$is_valid = $is_valid_get && $is_valid_post;

			return $is_valid ? true : $this->errors;
		}
	}