<?php
namespace Packaged\Dal\Ql\Cql;

use Packaged\Dal\Ql\Cql\DataType\BooleanType;
use Packaged\Dal\Ql\Cql\DataType\DoubleType;
use Packaged\Dal\Ql\Cql\DataType\FloatType;
use Packaged\Dal\Ql\Cql\DataType\IntegerType;
use Packaged\Dal\Ql\Cql\DataType\LongType;
use Packaged\Dal\Ql\QlDao;
use Packaged\DocBlock\DocBlockParser;

class CqlDao extends QlDao
{
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
          function ($arg)
          {
            return IntegerType::pack($arg);
          },
          function ($arg)
          {
            return IntegerType::unpack($arg);
          }
        );
      }
      else if($this->_hasAnyTag($docblock, ['double']))
      {
        $this->_addCustomSerializer(
          $property,
          'type',
          function ($arg)
          {
            return DoubleType::pack($arg);
          },
          function ($arg)
          {
            return DoubleType::unpack($arg);
          }
        );
      }
      else if($this->_hasAnyTag($docblock, ['bigint', 'counter', 'timestamp']))
      {
        $this->_addCustomSerializer(
          $property,
          'type',
          function ($arg)
          {
            return LongType::pack($arg);
          },
          function ($arg)
          {
            return LongType::unpack($arg);
          }
        );
      }
      else if($this->_hasAnyTag($docblock, ['float']))
      {
        $this->_addCustomSerializer(
          $property,
          'type',
          function ($arg)
          {
            return FloatType::pack($arg);
          },
          function ($arg)
          {
            return FloatType::unpack($arg);
          }
        );
      }
      else if($this->_hasAnyTag($docblock, ['bool']))
      {
        $this->_addCustomSerializer(
          $property,
          'type',
          function ($arg)
          {
            return BooleanType::pack($arg);
          },
          function ($arg)
          {
            return BooleanType::unpack($arg);
          }
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
}
