<?php

require_once "./View/View.php";
require_once "ControllerAbs.php";
require_once "./Model/ProductoModel.php";
require_once "./Model/MarcaModel.php";
require_once "./Model/UsuarioModel.php";
require_once "./ApiREST/Model/ComentarioModel.php";

class ViewController extends ControllerAbs
{

    private $view;
    private $logueado;
    private $user;
    private $productoModel;
    private $marcaModel;
    private $userModel;
    private $comentarioModel;

    //paginacion
    private $npaginacion;
    private $cantpag;

    function __construct()
    {
        parent::__construct();
        $this->view = new View();
        $this->productoModel = new ProductoModel();
        $this->marcaModel = new MarcaModel();
        $this->userModel = new UsuarioModel();
        $this->comentarioModel = new ComentarioModel();
        if ($this->helper->isLogged()) {
            $this->user = $this->helper->getLoggedUserName();
            $this->logueado = true;
        }
        $this->npaginacion = 5; //es "constante", sirve para cambiar la cantidad de items que se muestran en la paginacion
        $this->cantpag = 0; //se modifica dinamicamente
    }

    function
    default()
    {
        //sirve para limpiar la url y redirigir a home en caso de encontrar una ruta sin setear
        header("Location: " . HOME);
    }

    function Home()
    {
        $this->view->showHome(null, null, null, $this->user, $this->logueado);
    }

    function Buscar()
    {
        if (isset($_GET["busqueda"]) && isset($_GET["columna_db"])) {
            $busqueda = $_GET["busqueda"];
            $columna_db = $_GET["columna_db"];
            $productos = $this->productoModel->getProductosFiltrados(strtolower($columna_db), $busqueda);
            if (empty($productos)) {
                $this->default();
                die();
            }
            $marcas = $this->marcaModel->getMarcas();
            if ($columna_db != "precio")
                $busqueda = $columna_db . " " . strtoupper($busqueda);
            else
                $busqueda = $columna_db . " menor a " . strtoupper($busqueda);
            $this->view->showHome($productos, $busqueda, $marcas, $this->user, $this->logueado);
        } else {
            $this->default();
        }
    }

    function Catalogo()
    {
        $marcas = $this->marcaModel->getMarcas();
        //valores default de la paginacion
        $inicio = 0;
        $pagina = 1;
        //la consulta devuelve $npaginacion elementos, partiendo del elemento en la posicion $inicio de la db
        $productos = $this->productoModel->getProductosLimitados($inicio, $this->npaginacion);
        $allproductos = $this->productoModel->getProductos();
        $this->cantpag = floor(count($allproductos) / $this->npaginacion);
        $this->view->showCatalogo($productos, $allproductos, $pagina, $this->cantpag, $marcas, $this->user, $this->logueado);
    }

    function navegacionCatalogo()
    {
        if (isset($_GET["page"])) {
            $marcas = $this->marcaModel->getMarcas();
            $allproductos = $this->productoModel->getProductos();
            $this->cantpag = floor(count($allproductos) / $this->npaginacion);
            $pagina = $_GET["page"];
            if ($pagina == null)
                $pagina = 1;
            else
                $pagina = intval($pagina);
            if ($pagina <= 1) {
                $pagina = 1;
                $inicio = 0;
            } else if ($pagina >= $this->cantpag) {
                $pagina = $this->cantpag;
                $inicio = $this->npaginacion * $this->cantpag;
            } else
                $inicio = $this->npaginacion * $pagina;
            $productos = $this->productoModel->getProductosLimitados($inicio, $this->npaginacion);
            $this->view->showCatalogo($productos, $allproductos, $pagina, $this->cantpag, $marcas, $this->user, $this->logueado);
        } else
            header("Location: " . CATALOGO);
    }

    public function setNpaginacion($npaginacion = 5)
    {
        try {
            $this->npaginacion = intval($npaginacion);
        } catch (Exception $exc) {
            //se esperaba un entero
        }
    }

    function Administrar()
    {
        //compruebo que es el usuario logeado
        $this->helper->checkLoggedIn();
        $productos = $this->productoModel->getProductos();
        $marcas = $this->marcaModel->getMarcas();
        $usuarios = $this->userModel->getUsuarios($this->user);
        $usuarioactual = $this->userModel->getUsuarioPorNombre($this->user);
        $this->view->showAdministrator($productos, $marcas, $usuarios, $this->user, $this->logueado, $usuarioactual);
    }

    function showEditarProducto($params = null)
    {
        //compruebo que es el usuario logeado
        $this->helper->checkLoggedIn();
        $producto_id = $params[':ID'];
        $producto = $this->productoModel->getProductoPorID($producto_id);
        $marcas = $this->marcaModel->getMarcas();
        $usuarioactual = $this->userModel->getUsuarioPorNombre($this->user);
        $this->view->showEditarProducto($producto_id, $marcas, $producto, $this->logueado, $usuarioactual);
    }

    function showEditarMarca($params = null)
    {
        //compruebo que es el usuario logeado
        $this->helper->checkLoggedIn();
        $marca_id = $params[':ID'];
        $marca = $this->marcaModel->getMarcaPorID($marca_id);
        $this->view->showEditarMarca($marca_id, $marca, $this->logueado);
    }

    function iniciarSesion()
    {
        if (!$this->logueado) {
            $this->view->showLogin($this->logueado, false);
        } else {
            $this->default();
        }
    }

    function Registrarse()
    {
        if (!$this->logueado) {
            $this->view->showRegister($this->logueado, false);
        } else {
            $this->default();
        }
    }

    function showVerMas($params = null)
    {
        $producto_id = $params[':ID'];
        $producto = $this->productoModel->getProductoPorID($producto_id);
        if (!empty($producto)) {
            $marca = $this->marcaModel->getMarcaPorID($producto->id_marca);
            $usuario = $this->userModel->getUsuarioPorNombre($this->user);
            $comentarios = $this->comentarioModel->getComentariosPorIdProducto($producto_id);
            if (!empty($comentarios)) {
                $suma = 0;
                $cant = 0;
                foreach ($comentarios as $i) {
                    $suma += $i->puntaje;
                    $cant++;
                }
                $promedio = $suma / $cant;
            } else
                $promedio = 0;
            $this->view->verMas($producto, $marca, $this->logueado, $this->user, $usuario, round($promedio));
        } else {
            header("Location: " . CATALOGO);
        }
    }
}
