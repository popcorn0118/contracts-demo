<?php

namespace YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl;

/**
 * This interface acts as a flag that indicates the class can be serialized to
 * an expression descriptor for the Style Generator's JS component by calling
 * json_serialize() on it.
 */
interface SerializableToJsExpression extends \JsonSerializable {
}