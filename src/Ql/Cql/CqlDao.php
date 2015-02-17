<?php
namespace Packaged\Dal\Ql\Cql;

use Packaged\Dal\Ql\Cql\DataType\BooleanType;
use Packaged\Dal\Ql\Cql\DataType\DoubleType;
use Packaged\Dal\Ql\Cql\DataType\FloatType;
use Packaged\Dal\Ql\Cql\DataType\IntegerType;
use Packaged\Dal\Ql\Cql\DataType\LongType;
use Packaged\Dal\Ql\QlDao;
use Packaged\DocBlock\DocBlockParser;

abstract class CqlDao extends QlDao
{
  public function getTtl()
  {
    return null;
  }

  public function getTimestamp()
  {
    return null;
  }

  protected function _configure()
  {
    parent::_configure();
    foreach($this->getDaoProperties() as $property)
    {
      $docblock = DocBlockParser::fromProperty($this, $property);
      if($this->_hasAnyTag($docblock, ['int', 'smallint', 'integer']))
      {
        $this->_addCustomSerializer(
          $property,
          'type',
          function ($d) { return $d === null ? null : (int)$d; },
          [IntegerType::class, 'unpack']
        );
      }
      else if($this->_hasAnyTag($docblock, ['double']))
      {
        $this->_addCustomSerializer(
          $property,
          'type',
          function ($d) { return $d === null ? null : (double)$d; },
          [DoubleType::class, 'unpack']
        );
      }
      else if($this->_hasAnyTag($docblock, ['bigint', 'counter', 'timestamp']))
      {
        $this->_addCustomSerializer(
          $property,
          'type',
          function ($d) { return $d === null ? null : (int)$d; },
          [LongType::class, 'unpack']
        );
      }
      else if($this->_hasAnyTag($docblock, ['float']))
      {
        $this->_addCustomSerializer(
          $property,
          'type',
          function ($d) { return $d === null ? null : (float)$d; },
          [FloatType::class, 'unpack']
        );
      }
      else if($this->_hasAnyTag($docblock, ['bool']))
      {
        $this->_addCustomSerializer(
          $property,
          'type',
          function ($d) { return $d === null ? null : (bool)$d; },
          [BooleanType::class, 'unpack']
        );
      }
      else
      {
        $this->_addCustomSerializer(
          $property,
          'type',
          function ($d)
          {
            return $d === null ? null : (is_scalar($d) ? (string)$d : $d);
          },
          function ($d) { return $d; }
        );
      }
    }
  }

  protected function _hasAnyTag(DocBlockParser $block, array $tags)
  {
    foreach($tags as $tag)
    {
      if($block->hasTag($tag))
      {
        return true;
      }
    }
    return false;
  }

  /**
   * @param string|object|null $class
   *
   * @return CqlDaoCollection
   */
  protected static function _createCollection($class = null)
  {
    if($class === null)
    {
      $class = get_called_class();
    }

    return CqlDaoCollection::create($class);
  }
}
