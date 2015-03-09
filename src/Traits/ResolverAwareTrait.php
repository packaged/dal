<?php
namespace Packaged\Dal\Traits;

use Packaged\Dal\IConnectionResolver;

trait ResolverAwareTrait
{
  protected $_resolver;

  public function setResolver(IConnectionResolver $resolver)
  {
    $this->_resolver = $resolver;
    return $this;
  }
}
