@extends('layouts.master')
@section('content')
<img src="/img/StatusBar_1.png">
<div class="row align-middle">
	<div class="medium-3 align-center columns">
		<img id="welcome-logo" src="/img/logo_with_name.png">
	</div>
	<div class="medium-9 columns" id="welcome-text">
		<div class="row">
			<h4><b>CellFie</b>: <b>C</b>ellular <b>F</b>unctions <b>I</b>nferenc<b>E</b></h4>
		</div>
		<div>
			<b>Model-based Inference of Cellular Functions</b><br><br>
			An alternative approach to capture the breadth of cellular functions by performing a
			functional analysis of existing biological networks. We curated hundreds of metabolic functions from literature and defined them with regard to their metabolic pathways usage in existing genome-scale models of mammalian
			metabolism.	This platform can be used to comprehensively quantify the propensity of a cell line or
			tissue to be responsible for a metabolic function.<br>
			<br><br><br><br>
		</div>
		<div class="row">
			<br>
			Please enter a name for your run below.<br>
			Please enter an email address if you would like to receive a notification email after completion (optional).
		</div>
		<div class="row collapse">
			<div class="small-12 columns">
				<div class="callout">
					<form action="/createRun" method="post" class="custom" enctype="multipart/form-data">
						<input type="hidden" name="_token" value="{{ csrf_token() }}">
						<div class="row">
							<div class="medium-4 columns">
								<label for="name"><span class="has-tip tip-top whiteTxt" title="Please give this data a name">Name this Run</span></label>
								<input id="name" name="name" type="text"/>
							</div>
							<div class="medium-4 columns">
								<label for="ref"><span class="has-tip tip-top whiteTxt" title="Choose the reference model">Reference Model</span></label>
								<select id="ref" name="ref">
								  <option value="MT_recon_2_2_entrez.mat">Human (Recon2.2)</option>
								  <option value="MT_recon_2.mat">Human (Recon2)</option>
								  <option value="MT_recon_1.mat">Human (Recon1)</option>
								  <option value="MT_iHsa.mat">Human (iHsa)</option>
								  <option value="MT_quek14.mat">Human (quek14)</option>
								  <option value="MT_iRno.mat">Rat (iRno)</option>
								  <!-- <option value="MT_inesMouseModel.mat">Mouse (ines)</option> -->
								  <option value="MT_iMM1415.mat">Mouse (iMM1415)</option>
<!-- 								  <option value="Cho_gem">CHO (GeM)</option> -->
								  <option value="MT_iCHOv1_final.mat">CHO (iCHOv1)</option>
								</select>
							</div>
							<div class="medium-4 columns">
								<label for="email"><span class="has-tip tip-top whiteTxt" title="The e-mail address we should send your results to">E-mail Address (optional)</span></label>
								<input id="email" name="email" type="text"/>
							</div>
						</div>

						<div class="row align-right">
							<div class="shrink columns">
								<input type="submit" id="runSubmitButton" class="medium success button expand"/>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>

  

@stop
@section('customCSS')
	<style type="text/css">
		#welcome-text{
			min-height: 340px;
			justify-content: space-between;
	    flex-direction: column;
	    display: flex;
	    padding-top: 1em;
	    padding-bottom: 1em;
		}
		#welcome-logo{
			height: 340px;
			width: 340px;
		}
		#how-to-img {
			margin-left: 2em;
		}
		.row {
			max-width: 82.5rem;
		}
		.callout input[type='submit'] {
			margin-bottom: 0;
		}
		.callout {
			margin-bottom: 0;
		}
		.footer {
			/*position: absolute;
			  right: 0;
			  bottom: 0;
			  left: 0;
			  padding: 1rem;
			  background-color: #efefef;
			  text-align: center;*/
			margin-top: 6em;
		}
	</style>
@stop