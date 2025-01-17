<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Run;

use Illuminate\Support\Facades\DB;

class RunController extends Controller
{
	/**
	 * Create a new controller instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		//
	}

	// Create a run. 
	public function postCreate(Request $request)
	{
		$this->validate($request, 
			[ "email" => "email|nullable",
				"name" => "required|string",
				"ref" => "required|string"
			]
		);

		$email = $request->input('email');
		$name = $request->input('name');
		$ref = $request->input('ref');

		$run = Run::create( [
			'email'=>$email,
			'name'=>$name,
			'ref'=>$ref,
			'status'=> 'uploading',
			'dir' => time(),
			'data_dir' => time()
		]);
		$run->dir=$run->dir.'_'.$run->id;
		$dataDir = storage_path("data/$run->dir");
		$run->data_dir=$run->dir;
		$run->save();

		$dir = $run->directory();
		makeDir( $dir, 0775 );
		makeDir( $dir."/workingDir", 0775 );
		makeDir( $dataDir, 0775 );
		// makeDir( $dir."/workingDir/Data", 0775 );
		`ln -s $dataDir $dir/workingDir/Data`;
		makeDir( $dir."/workingDir/Library", 0775 );

		if (!empty($run->email)) {
			\Illuminate\Support\Facades\Mail::to($run->email)->queue(new \App\Mail\RunCreated($run));
		}
		$CleanupRunJob = (new \App\Jobs\CleanupRun($run))
		                    ->delay(\Carbon\Carbon::now()->addDays(2));
		dispatch($CleanupRunJob);
		
		return redirect("/upload/$run->dir");
	}

	/**
	 * Restart a run and redirect to parameters page
	 * @param  [type] $hash [description]
	 * @return redirect 		/parameters/$hash
	 */
	public function postRestart(Request $request, $hash)
	{
		// If they are rerunning the example run, create a dummy oldRun
		if ($hash == 'example-run') {
			$oldRun = (object)[
				'name'=>'example-run',
				'email'=>null,
				'data_dir'=>'example-run',
			];
			$oldPath = storage_path('runs/example-run');
		}
		else {
			$oldRun = Run::where('dir',$hash)->firstOrFail();
			$oldPath = $oldRun->directory();
			
		}
		$name = $request->input('name');
		if (empty($name)) {
			$name = $oldRun->name;
			if (preg_match('/(.* rerun_)([1-9]+)$/', $name, $matches)){
				$name = $matches[1] . ($matches[2]+1);
			}
			else {
				$name = $name . ' rerun_1';
			}
		}
		$newRun = Run::create( [
			'email'=>$oldRun->email,
			'name'=>$name,
			'ref'=>$oldRun->ref,
			'status'=> 'setting-parameters',
			'dir' => time(),
			'data_dir'=>$oldRun->data_dir
		]);

		$newRun->dir=$newRun->dir.'_'.$newRun->id;
		$newRun->save();

		$newPath = $newRun->directory();

		makeDir( $newPath, 0775 );
		makeDir( $newPath."/workingDir", 0775 );
		makeDir( $newPath."/workingDir/Library", 0775 );

		
		$newDataPath = $newPath.'/workingDir/Data';
		$oldDataPath = storage_path("/data/$oldRun->data_dir");
		`ln -s $oldDataPath $newDataPath`;
		`cp $oldPath/workingDir/DataSheet.xlsx $newPath/workingDir/DataSheet.xlsx`;

		$CleanupRunJob = (new \App\Jobs\CleanupRun($newRun))
		                    ->delay(\Carbon\Carbon::now()->addDays(2));
		dispatch($CleanupRunJob);

		return redirect('/parameters/'.$newRun->dir);
	}

	// Prevent lock recipe from further uploads, start the run. 
	public function postStart(Request $req, $hash)
	{
		$rules = [];
		foreach (config('cellfie_config.parameter_groups') as $groupname => $parameters) {
			foreach ($parameters as $param_name => $param_properties) {
				$rules = array_add($rules, $param_name, $param_properties['rules']."|nullable");
			}
		}
		$errorMessages = ['between' => 'The :attribute must be between :min - :max.'];
		$this->validate($req, $rules, $errorMessages);
		try {
			DB::beginTransaction();

			$run = Run::where('dir',$hash)->firstOrFail();

			$dir = $run->directory();
			File::put("$dir/status.log", 'Queued');


	    $data = $req->all();
	    $this->generateConfig($req, $run->directory(),$hash);

	    $startRunScript = app()->basePath()."/app/Scripts/startRun.sh";
//	    $dockerImage = config('docker.image');
//	    $numCores = config('docker.num_cores');
	    $dataPath = storage_path("data/$run->data_dir");
	    $runCmd = "bash $startRunScript $dir $dataPath > $dir/runStatus.log";
	    File::put("$dir/runCmd.sh", $runCmd);
	    $runsInQueue = Run::where('status','queued')->exists() || Run::where('status','running')->exists();
	    if (! $runsInQueue) {
				dispatch((new \App\Jobs\StartRun($run))->onQueue("start_run"));
		    $run->status='running';
	    }
	    else{
		    $run->status='queued';
	    }
	    $run->save();

	    DB::commit();
		} catch (Exception $e) {
			DB::rollBack();
			Log::error($e);
		}


		return redirect("/run/$hash");
	}

	public function postDoneUploading($hash)
	{
		$run = Run::where('dir',$hash)->firstOrFail();
		$run->status='setting-parameters';
		$run->save();
		// check that uploaded genes are in the model and display in files.blade.php
		return redirect("/parameters/$hash");
	}

	public function postConfigureFiles(Request $request, $hash)
	{
		$validator = \Validator::make($request->all(), [
		    'group-*' => ['required', \Illuminate\Validation\Rule::in(['control','treatment'])]
		]);
		$run = Run::where('dir',$hash)->firstOrFail();
		$runDir = $run->directory();
		$dataDir = $runDir."/workingDir/Data";
		$files = File::files($dataDir);
		$numControl = $numTreatment = 0;
		// For generating excel sheet
		$excelRows = [];
		// Map of files, group, index
		foreach ($files as $file) {
			$basename = basename($file);
			$groupInputName = "group-".str_replace(".", "_", $basename);
			$renameInputName = "rename-".str_replace(".", "_", $basename);
			$group = $request->input($groupInputName) ?? 'treatment';
			if ($group == 'control') {
				$prefix = "Control";
			}
			else{
				$prefix = $request->input($renameInputName,"Treatment");
				if (empty($prefix)) {
					$prefix = "Treatment";
				}
			}
			array_push($excelRows, ['FILENAME'=>$basename, 'TREATMENT' => $prefix]);
		}

		\Excel::create('DataSheet', function($excel) use (&$excelRows){

		    $excel->sheet('Sheet1', function($sheet) use (&$excelRows){

	          $sheet->with($excelRows, null, 'A1', false, false);

	      });

		})->save('xlsx',"$runDir/workingDir/");

		$run->status = "setting-parameters";
		$run->save();
		return redirect("/parameters/$hash");
	}

	public function generateConfig($req, $dir,$hash)
	{


		$config = "";
		$libFilename = $req->input("LibFilename");
		$customFile;
		$customFilename;
		if ($libFilename == 'custom') {
			$customFile = $req->file('custom-lib-file');
			$customFilename = $customFile->getClientOriginalName();
			\Log::debug("Custom File selected, name: ".$customFilename);
		}
		foreach (config('cellfie_config.parameter_groups') as $groupname => $parameters) {
			$config.= "# $groupname \n";
			if ($groupname == "Library Parameters") {
				if ($libFilename=='custom') {
					if ($req->hasFile('custom-lib-file')) {
						\Log::debug("Lib file provided. Moving.");
						$customFile->move("$dir/workingDir/Library/",$customFilename);
					}
					else {
						\Log::error("Custom Lib selected, but no file provided");
					}
				}
				else{
					$folderName = config('cellfie_config.libraries')[$libFilename];
					$libPath = resource_path("libraries/$folderName");
					$libParameters = File::get("$libPath/library_parameters.txt");
					$bowTieCopySuccessful = File::copyDirectory("$libPath/Bowtie2_Index", "$dir/workingDir/Library/Bowtie2_Index");
					File::copy("$libPath/$libFilename", "$dir/workingDir/Library/$libFilename");
					$config.= "$libParameters\n";
					continue;
				}
			}
			foreach ($parameters as $param_name => $param_properties) {
				if ($param_name == "LibFilename"&& $libFilename =="custom") {
					\Log::debug("In LibFilename ifblock");
					$value = $customFilename;
				}
				else {
					if (!empty($req->input($param_name))) {
						$value = $req->input($param_name);
					}
					else {
						$value = $param_properties['default'];
					}
				}
				if ($param_properties['in_quotes']) {
					$config .= "$param_name".":"." '$value' \n";
				}
				else {
					$config .= "$param_name".":"." $value \n";
				}
			}
			$config.="\n";
		}
#		$config .= "ref:".DB::select('select ref from run where hash == $hash')."\n";
		$run = Run::where('dir',$hash)->firstOrFail();
		$config .= "ref:"." '$run->ref' \n";

		$config .= config('cellfie_config.directories');
		$config .= "\n";
		$config .= config('cellfie_config.script_filenames');

		File::put("$dir/workingDir/configuration.yaml", $config);
		File::put("$dir/workingDir/output.log", "");
	}

	public function getRuns()
	{
		return Run::all();
	}
	

	public function tailLog(){
		
	}
}
