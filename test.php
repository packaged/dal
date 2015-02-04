<?php
namespace Testing;

use Packaged\Dal\Ql\QlDao;
use Packaged\QueryBuilder\Assembler\MySQL\MySQLAssembler;

class Test
{
  public function run()
  {

    var_dump_json(User::loadWhere([]));

    /*    var_dump_json(User::loadById(4));

        return;*/

    $user       = new User();
    $user->name = 'Test';
    $user->save();

    $user->name = 'Testing';
    $user->save();

    $user->delete();

    $user     = new User();
    $user->id = 4;
    $user->load();

    $tbUsers = User::loadWhere(['name' => ['Test', 'Testing']]);
    foreach($tbUsers as $user)
    {
      echo "Located $user->name\n";
    }

    $users = User::collection();
    echo "\n\t" . MySQLAssembler::stringify($users->getQuery()) . "\n";
    var_dump($users->count());
    $users->where(['name' => 'Brooke']);
    var_dump($users->count());
    $users->resetQuery();
    var_dump($users->min('id'));
    var_dump($users->max('id'));
    var_dump($users->sum('id'));
    var_dump($users->avg('id'));

    var_dump_json($users->distinct('name'));
  }
}

class User extends QlDao
{
  protected $_dataStoreName = 'users';
  public $id;
  public $name;
}
