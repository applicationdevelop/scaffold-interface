<?php

namespace Amranidev\ScaffoldInterface\Http\Controllers;

use Amranidev\Ajaxis\Ajaxis;
use Amranidev\ScaffoldInterface\Attribute;
use Amranidev\ScaffoldInterface\Datasystem\Database\DatabaseManager;
use Amranidev\ScaffoldInterface\Scaffold;
use Amranidev\ScaffoldInterface\Scaffoldinterface;
use AppController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Session;
use URL;

/**
 * Class GuiController.
 *
 * @author  Amrani Houssain <amranidev@gmail.com>
 */
class GuiController extends AppController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $scaffold = Scaffoldinterface::paginate(6);
        $scaffoldList = DatabaseManager::tableNames();

        return view('scaffold-interface::scaffoldApp', compact('scaffold', 'scaffoldList'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        app()->make('Request')->setRequest($request->toArray());
        $scaffold = app()->make('Scaffold');
        $scaffold->migration()->model()->controller()->route()->views();
        $paths = app()->make('Path');
        $names = app()->make('NamesGenerate');
        $scaffoldInterface = new Scaffoldinterface();

        $scaffoldInterface->migration = $paths->migrationPath;
        $scaffoldInterface->model = $paths->modelPath();
        $scaffoldInterface->controller = $paths->controllerPath();
        $scaffoldInterface->views = $paths->dirPath();
        $scaffoldInterface->tablename = $names->tableNames();
        $scaffoldInterface->package = config('amranidev.config.package');
        $scaffoldInterface->save();

        Session::flash('status', 'Created Successfully'.$names->tableName());

        return redirect('scaffold');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $scaffoldInterface = Scaffoldinterface::FindOrFail($id);

        unlink($scaffoldInterface->migration);
        unlink($scaffoldInterface->model);
        unlink($scaffoldInterface->controller);
        unlink($scaffoldInterface->views.'/index.blade.php');
        unlink($scaffoldInterface->views.'/create.blade.php');
        unlink($scaffoldInterface->views.'/show.blade.php');
        unlink($scaffoldInterface->views.'/edit.blade.php');
        rmdir($scaffoldInterface->views);

        //Clear Routes Resources
        $this->clearRoutes(lcfirst(str_singular($scaffoldInterface->tablename)));

        $scaffoldInterface->delete();

        Session::flash('status', 'Deleted Successfully');

        return URL::to('scaffold');
    }

    /**
     * Delete confirmation message by ajaxis.
     *
     * @link https://github.com/amranidev/ajaxis
     *
     * @return \Illuminate\Http\Response
     */
    public function deleteMsg($id)
    {
        $scaffold = Scaffoldinterface::FindOrFail($id);

        if (Schema::hasTable($scaffold->tablename)) {
            $table = $scaffold->tablename;

            return view('scaffold-interface::template.DeleteMessage.delete', compact('table'))->render();
        }

        $msg = Ajaxis::Mtdeleting('Warning!!', "Would you like to delete {$scaffold->tablename} MVC files ??", '/scaffold/guirollback/'.$id);

        return $msg;
    }

    /**
     * Get attributes.
     *
     * @param string $table
     *
     * @return \Illuminate\Http\Response
     */
    public function getResult($table, Request $request)
    {
        $attributes = new Attribute($table);

        if ($request->ajax()) {
            return $attributes->getAttributes();
        }
    }

    /**
     * Migrate schema.
     *
     * @return \Illuminate\Http\Response
     */
    public function migrate()
    {
        try {
            Artisan::call('migrate', ['--path' => config('amranidev.config.database')]);

            exec('cd '.base_path().' && composer dump-autoload');
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        $Msg = str_replace("\n", '', Artisan::output());

        Session::flash('status', $Msg);

        return redirect('scaffold');
    }

    /**
     * Rollback schema.
     *
     * @throws Exception
     *
     * @return \Illuminate\Http\Response
     */
    public function rollback()
    {
        try {
            if (!Scaffoldinterface::all()->count()) {
                throw new \Exception('Nothing to rollback');
            }

            Artisan::call('migrate:rollback');
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        $Msg = str_replace("\n", '', Artisan::output());

        Session::flash('status', $Msg);

        return redirect('scaffold');
    }

    /**
     * Clear routes.
     *
     * @param string $remove
     *
     * @return mixed
     */
    private function clearRoutes($remove)
    {
        $path = config('amranidev.config.routes');

        $lines = file($path, FILE_IGNORE_NEW_LINES);

        foreach ($lines as $key => $line) {
            if (strstr($line, $remove)) {
                unset($lines[$key]);
            }
        }

        $data = implode("\n", array_values($lines));

        return file_put_contents($path, $data);
    }

    /**
     * Generate dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function dashboard()
    {
        $scaffoldList = Scaffoldinterface::all();

        return view('scaffold-interface::template.Dashboard.Dashboard', ['Parse' => $scaffoldList]);
    }

     /**
      * ManyToMany form.
      *
      * @param \Illuminate\Http\Request
      *
      * @return \Illuminate\Http\Response
      */
     public function manyToManyForm(Request $request)
     {
         $dummyData = DatabaseManager::tableNames();
         $elements = Ajaxis::MtcreateFormModal([
            ['type' => 'select', 'name' => 'table1', 'key' => 'table1', 'value' => $dummyData],
            ['type' => 'select', 'name' => 'table2', 'key' => 'table2', 'value' => $dummyData], ], '/scaffold/manyToMany', 'Many To Many');

         return $elements;
     }

    /**
     * ManyToMany generate.
     *
     * @param \Illuminate\Http\Request
     *
     * @return \Illuminate\Http\Response
     */
    public function manyToMany(Request $request)
    {
        if ($this->check($request->toArray())) {
            Session::flash('status', 'Error! can not be related');

            return redirect('scaffold');
        }

        $manytomany = new \Amranidev\ScaffoldInterface\ManyToMany\ManyToMany($request->except('_token'));

        $manytomany->burn();

        Session::flash('status', 'ManyToMany generated successfully');

        return redirect('/scaffold');
    }

    /**
     * Check ManyToMany request if is it available to use.
     *
     * @param array $request
     *
     * @return mixed
     */
    private function check(array $request)
    {
        return $request['table1'][0] == $request['table2'][0] ? true : false;
    }
}
