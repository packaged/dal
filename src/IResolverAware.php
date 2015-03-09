<?php
namespace Packaged\Dal;

interface IResolverAware
{
  public function setResolver(IConnectionResolver $resolver);
}
