<!DOCTYPE html>
<html lang="es" ng-app="ng_app_info">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>OCCT::Información del lector</title>
	<% HTML::style('css/bootstrap.min.css'); %>
	<% HTML::style('css/default.css'); %>
</head>
<body>

	<div class="navbar navbar-fixed-top">
	    <div class="navbar-header">
			<a class="navbar-brand" href="/" title="Open Canarias, Control de Tiempo">OCCT</a>
	    </div>
	    <div class="collapse navbar-collapse">
	        <ul class="nav navbar-nav navbar-right">
	          <li class="dropdown">
	            <a href="" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><% Auth::user()->usu_name %> <span class="caret"></span> &nbsp;&nbsp;</a>
	            <ul class="dropdown-menu" role="menu">
	              <li><a href="logout">Cerrar sesión</a></li>
	            </ul>
	          </li>
	        </ul>
		  </div>
	</div>

	<div class="container" ng-controller="InfoController">
		<div class="col-md-4">

			<div class="panel panel-default">
			  <div class="panel-heading">
			    <h3 class="panel-title">Lector</h3>
			  </div>
			  <div class="panel-body" ng-show="lector != undefined" ng-cloak>{{ lector.nombre }} &nbsp;</div>
			  <div class="panel-body" ng-show="lector == undefined">Lector no identificado. &nbsp;</div>
			</div>

		</div>
		<div class="col-md-8">

			<div class="panel panel-default">
			  <div class="panel-heading">Terminal</div>
			  <div class="panel-body">
			  	Biblioteca: {{ biblioteca.nombre }} &nbsp;
			  	<hr/>
			  	Terminal: {{ terminal.id }}
			  	</br>
			  	</br>
			  	Tiempo de uso restante: {{ terminal.timetolive }} min.  &nbsp;
			  	<hr/>
			  	<u>Limites establecidos para el lector</u>
			  	</br>
			  	Limite semanal: {{ lector.limite_semanal }}
			  	<br/>
			  	Limite diario: {{ lector.limite_diario }}
			  	</br>
			  </div>
			</div>

		</div>
	</div>
        <!--
	<div align="center">
	        <button type="button" class="btn btn-default" onclick="window.open('', '_self',''); window.close();">Comenzar a usar el terminal</button>
	</div>
	-->
	<% HTML::script('js/jquery.min.js'); %>
	<% HTML::script('js/bootstrap.min.js'); %>
	<% HTML::script('js/underscore-min.js'); %>
	<% HTML::script('js/angular.min.js'); %>
	<% HTML::script('js/frontend_info.js'); %>
</body>
</html>
