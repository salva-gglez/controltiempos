<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Control tiempos</title>
        <% HTML::style('css/bootstrap.min.css'); %>
        <% HTML::style('css/default.css'); %>
        <script type="text/javascript">
        	function disableUserName() {
			document.getElementById('textUserName').disabled = 'disabled';
			document.getElementById('textPassword').disabled = 'disabled';
			document.getElementById('checkboxPoliticas').disabled = true;
			document.getElementById('Bloqueado').style.display = 'block';
		}
        </script>
	<meta http-equiv="refresh" content="60" /> 
    </head>
    <body>
        <div>
            <div class="col-md-9">Biblioteca: <% $biblioteca->nombre %></div>
            <div class="col-md-3">
                <div>Terminal: <% $terminal->id %></div>
                <div>Estado: <% $terminal->status %></div>
                <%-- <div>Bloqueado: <% $terminal->disabled %></div> --%>
            </div>
        </div>
        <div style="display:none" class="center" id="Bloqueado">
		<h1>Terminal Bloqueado</h1>
	</div>
	
	        <div class="container">
            <div class="col-md-4">
            </div>
            <div class="col-md-8">

            <div class="panel panel-default">
              <div class="panel-body">
                 <div class="checkbox">
                      <label>
                      <input type="checkbox" id="checkboxPoliticas" onclick="$('#submit').prop('disabled', !this.checked)" />
                      Acepto las politicas de uso
                      </label>
                 </div>
                 <div class="a">
                      <button type="button" class="btn btn-info btn-lg" data-toggle="modal" data-target="#myModal">Politicas de uso</button>
                      <!-- <label>
                       
                       <a href="docs/PoliticaUso.pdf" target="_blank" /> 
                       <embed srv="docs/PoliticaUso.pdf" /> 
                      Politicas de uso
                      </label> -->
                 </div>
              </div>
            </div>
            </div>
        </div>
	
        <div class="container">
            <div class="col-md-4">

            </div>
            <div class="col-md-8">
                <div id="panel-login" class="panel panel-default">
                    <div class="panel-body">
                        <% Form::open(array('url' => '/login')) %>
                            <legend>Iniciar sesión</legend>
                            <div class="form-group">
                                <% Form::label('usuario', 'Nombre de usuario o e-mail de Absys') %>
                                <% Form::text('username', Input::old('username'), array('id' => 'textUserName', 'class' => 'form-control')); %>
                            </div>
                            <div class="form-group">
                                <% Form::label('contraseña', 'Contraseña') %>
                                <% Form::password('password', array('id' => 'textPassword', 'class' => 'form-control')); %>
                            </div>
                            <!--
                            <div class="checkbox">
                                <label>
                                    <% Form::checkbox('rememberme', true) %>
                                    Recordar contraseña
                                </label>
                            </div>
                            -->
                            <% Form::submit('Enviar', array('id' => 'submit', 'class' => 'btn btn-primary', 'onclick' => '$("#enviarDisabled").css("display", "none");$("#loading").css("display", "inline")', 'disabled' => 'disabled')) %>
			   <div id="enviarDisabled" style="display: inline">
				<i>(Debe aceptar las politicas de uso para continuar)</i>
			   </div>
<div style="display:none" id="loading">
           <img alt="Conectando con Absys.net" src="images/ajax-loader.gif" height="40" width="40" /><i>   Conectando con Absys.net</i>
</div>
                        <% Form::close() %>
                        <%-- Preguntamos si hay algún mensaje de error y si hay lo mostramos  --%>
                        @if(Session::has('mensaje_error'))
                            <div class="alert alert-danger"><b><% Session::get('mensaje_error') %></b></div>
                        @endif
                    </div>
                </div>
            </div>

        </div>


<!-- Modal -->
<div id="myModal" class="modal fade" role="dialog">
  <div class="modal-dialog">

    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Politicas de uso</h4>
      </div>
      <div class="modal-body">
	<h5>Politicas de Uso</h5>
	</br>
	</br>
	AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA</br></br>
	BBBBBBBBBBBBBBBBBBBBBBBBB
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
      </div>
    </div>

  </div>
</div>


        <div class="container">
            <div class="col-md-4">
            </div>
            <div class="col-md-8">

            <div class="panel panel-default">
              <div class="panel-body">
                Debe usted disponer de un usuario y contraseña en la lista de ABSYS o en la lista de usuarios 
                de las bibliotecas municipales. Si no es así, o ha olvidado la contraseña, hable con el personal
                de la biblioteca.
              </div>
            </div>
                
            </div>
        </div>

	<%-- En determinados casos bloqueamos el terminal --%>
        <?php
              if ( $terminal->disabled === 'true' ) {
                 echo "<script> disableUserName(); </script>";
              }
        ?>

        <% HTML::script('js/jquery.min.js'); %>
        <% HTML::script('js/bootstrap.min.js'); %>
        <script>
        $("#submit").click(function() {
            $("div.alert.alert-danger").remove();
        });
        </script>
    </body>
</html>
