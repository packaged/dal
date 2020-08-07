<?php
namespace Packaged\Dal\DataTypes;

class UniqueList implements IDataType, \JsonSerializable
{
  protected $_value = [];
  protected $_add = [];
  protected $_remove = [];

  protected $_adjusted = false;

  public function __construct(array $original)
  {
    $this->resetWithValue($original);
  }

  public function resetWithValue(array $original)
  {
    $this->_value = array_fill_keys($original, true);
    $this->_add = [];
    $this->_remove = [];
    $this->_adjusted = false;
    return $this;
  }

  public function current()
  {
    return array_keys($this->_value);
  }

  public function setValue(array $value)
  {
    $this->_value = array_fill_keys($value, true);
    $this->_add = [];
    $this->_remove = [];
    $this->_adjusted = true;
    return $this;
  }

  public function calculated()
  {
    return array_keys(array_diff_key(array_merge($this->_value, $this->_add), $this->_remove));
  }

  public function add(...$values)
  {
    foreach($values as $value)
    {
      unset($this->_remove[$value]);
      if(!isset($this->_value[$value]))
      {
        $this->_add[$value] = true;
      }
    }
    return $this;
  }

  public function remove(...$values)
  {
    foreach($values as $value)
    {
      unset($this->_add[$value]);
      if(isset($this->_value[$value]))
      {
        $this->_remove[$value] = true;
      }
    }
    return $this;
  }

  public function hasFixedValue()
  {
    return $this->_adjusted;
  }

  public function hasChanged()
  {
    return $this->_adjusted || !empty($this->_add) || !empty($this->_remove);
  }

  public function hasAdditions()
  {
    return !empty($this->_add);
  }

  public function getAdditions()
  {
    return $this->hasAdditions() ? array_keys($this->_add) : [];
  }

  public function hasRemovals()
  {
    return !empty($this->_remove);
  }

  public function getRemovals()
  {
    return $this->hasRemovals() ? array_keys($this->_remove) : [];
  }

  function jsonSerialize()
  {
    return (string)$this;
  }
}
