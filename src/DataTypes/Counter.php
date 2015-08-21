<?php
namespace Packaged\Dal\DataTypes;

class Counter implements IDataType, \JsonSerializable
{
  public function __construct($original)
  {
    $this->_value = $original;
    $this->_adjust = 0;
    $this->_adjusted = false;
  }

  protected $_adjusted = false;
  protected $_value = 0;
  protected $_adjust = 0;

  public function current()
  {
    return $this->_value;
  }

  public function setValue($value)
  {
    $this->_value = $value;
    $this->_adjust = 0;
    $this->_adjusted = true;
  }

  public function calculated()
  {
    return $this->_value + $this->_adjust;
  }

  public function increment($by = 1)
  {
    $this->_adjust += abs($by);
    $this->_adjusted = abs($by) > 0;
    return $this;
  }

  public function decrement($by = 1)
  {
    $this->_adjust -= abs($by);
    $this->_adjusted = abs($by) > 0;
    return $this;
  }

  public function isIncrement()
  {
    return $this->_adjusted && $this->_adjust > 0;
  }

  public function isDecrement()
  {
    return $this->_adjusted && $this->_adjust < 0;
  }

  public function isFixedValue()
  {
    return $this->_adjusted && $this->_adjust === 0;
  }

  public function hasChanged()
  {
    return (bool)$this->_adjusted;
  }

  public function getIncrement()
  {
    return $this->isIncrement() ? abs($this->_adjust) : 0;
  }

  public function getDecrement()
  {
    return $this->isDecrement() ? abs($this->_adjust) : 0;
  }

  public function __toString()
  {
    return (string)$this->_value;
  }

  function jsonSerialize()
  {
    return (string)$this;
  }
}
