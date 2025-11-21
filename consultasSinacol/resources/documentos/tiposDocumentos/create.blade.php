@extends('layouts.default', ['paceTop' => true])

@section('title', 'Tipo Documento')

@include('includes.component.datatables')

@section('content')
  <!-- begin breadcrumb -->
  <ol class="breadcrumb float-xl-right">
      <li class="breadcrumb-item"><a href="/">Home</a></li>
      <li class="breadcrumb-item"><a href="{!! route('tipo-documento.index') !!}">Administración</a></li>
      <li class="breadcrumb-item active">Usuarios</li>
  </ol>
  <!-- end breadcrumb -->
  <!-- begin page-header -->
  <h1 class="page-header">Administrar tipos documentos<small>Nuevo </small></h1>
  <!-- end page-header -->

<form action="{{ route('tipo-documento.store') }}" method="POST">
  @csrf
  <div class="panel panel-default">
      <!-- begin panel-heading -->
      <div class="panel-heading">
          <h4 class="panel-title">Nuevo tipo</h4>
          <div class="panel-heading-btn">
              <a href="{!! route('tipo-documento.index') !!}" class="btn btn-primary btn-sm"><i class="fa fa-arrow-alt-circle-left"></i> Regresar</a>
          </div>
      </div>
      <div class="panel-body">
        @include('documentos.tiposDocumentos._form')
      </div>
      <div class="panel-footer text-right">
          <a href="{!! route('tipo-documento.index') !!}" class="btn btn-white btn-sm"><i class="fa fa-times"></i> Cancelar</a>
          <button class="btn btn-primary btn-sm m-l-5"><i class="fa fa-save"></i> Guardar</button>
      </div>
  </div>
</form>
@endsection
