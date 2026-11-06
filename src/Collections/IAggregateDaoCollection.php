<?php
namespace Packaged\Dal\Collections;

use Packaged\Dal\IDaoCollection;

/**
 * @template T of \Packaged\Dal\IDao
 * @extends IDaoCollection<T>
 */
interface IAggregateDaoCollection extends IDaoCollection
{
  public function min($property = 'id');

  public function max($property = 'id');

  public function avg($property = 'id');

  public function sum($property = 'id');
}
