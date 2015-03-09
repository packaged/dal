<?php
namespace Packaged\Dal\Traits;

use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\DalException;
use Packaged\Dal\IConnectionResolver;

trait ResolverAwareTrait
{
  protected $_resolver;

  public function setResolver(IConnectionResolver $resolver)
  {
    $this->_resolver = $resolver;
    return $this;
  }

  /**
   * @return IConnectionResolver|DalResolver
   * @throws DalException
   */
  public function getResolver()
  {
    if($this->_resolver === null)
    {
      throw new DalException(
        "Connection running without the resolver being defined"
      );
    }
    return $this->_resolver;
  }
}
