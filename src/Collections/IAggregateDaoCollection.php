<?php
namespace Packaged\Dal\Collections;

use Packaged\Dal\IDaoCollection;

interface IAggregateDaoCollection extends IDaoCollection
{
  public function min($property = 'id');

  public function max($property = 'id');

  public function avg($property = 'id');

  public function sum($property = 'id');
}
