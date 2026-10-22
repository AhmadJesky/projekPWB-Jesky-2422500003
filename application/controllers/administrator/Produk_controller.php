<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Produk_controller extends CI_Controller
{
    
    public function __construct()
    {
        parent::__construct();
        is_admin_logged_in();
        // Load model
        $this->load->model('produk_model');
        $this->load->model('produk_kategori_model');
        $this->load->model('produk_gambar_model');
    }

    public function index()
    {
        $data['title'] = 'Daftar Produk';
        $data['list_produk'] = $this->produk_model->get_all();
        $this->load->view('administrator/templates/header', $data);
        $this->load->view('administrator/templates/sidebar');
        $this->load->view('administrator/produk/index');
        $this->load->view('administrator/templates/footer');
    }
    
    public function tambah_produk()
    {
        $data['title'] = 'Tambah Produk';
        $this->form_validation->set_rules('nama_produk', 'Nama produk', 'required');
        $this->form_validation->set_rules('kategori_produk', 'Kategori', 'required');
        if ($this->form_validation->run() !== FALSE) {
            $this->__simpan_produk();
        } else {
            $data['list_kategori'] = $this->produk_kategori_model->get_all();
            $this->load->view('administrator/templates/header', $data);
            $this->load->view('administrator/templates/sidebar');
            $this->load->view('administrator/produk/tambah_produk', $data);
            $this->load->view('administrator/templates/footer');
        }
    }

    private function __simpan_produk()
    {
        $data = [
            'nama' => ucwords($this->input->post('nama_produk')),
            'categori_id' => $this->input->post('kategori_produk'),
            'harga' => $this->input->post('harga_produk'),
            'stok' => $this->input->post('stok_produk'),
            'deskripsi' => ucfirst($this->input->post('deskripsi_produk'))
        ];

        #simpan dan return id_produk yang disimpan
        $id_produk = $this->produk_model->tambah($data);
        # simpan data, trus lakukan upload gambar kebalikan id produk

        $count = count($_FILES['gambar_produk']['name']);
        if ($count > 0) {
            // jika ada gambar upload
            $this->__produk_gambar_upload($count, $id_produk);
        }
        $this->session->set_flashdata('message', '
            <div class="alert alert-success d-flex align-items-center alert-dismissible fade show" role="alert">
                <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Success:">
                    <use xlink:href="#check-circle-fill" />
                </svg>
                <div>
                    Berhasil menambahkan produk!!
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        ');

        redirect('administrator/produk');
    }

    private function __produk_gambar_upload($count, $id_produk)
{
    $jumlah_berhasil = 0;
    $this->load->library('upload');

    for ($i = 0; $i < $count; $i++) {
        if (empty($_FILES['gambar_produk']['name'][$i])) {
            continue;
        }

        $_FILES['file']['name']     = $_FILES['gambar_produk']['name'][$i];
        $_FILES['file']['type']     = $_FILES['gambar_produk']['type'][$i];
        $_FILES['file']['tmp_name'] = $_FILES['gambar_produk']['tmp_name'][$i];
        $_FILES['file']['error']    = $_FILES['gambar_produk']['error'][$i];
        $_FILES['file']['size']     = $_FILES['gambar_produk']['size'][$i];

        $config['upload_path']   = './uploads/produk/';
        $config['allowed_types'] = 'jpg|jpeg|png|gif';
        $config['max_size']      = 5000;
        $config['file_name']     = 'produk-' . $id_produk . '-' . uniqid();

        $this->upload->initialize($config, TRUE);

        if ($this->upload->do_upload('file')) {
            $upload_data = $this->upload->data();

            // Cek apakah produk_id sudah memiliki record gambar di database
            $existing_image = $this->produk_gambar_model->get_by_produk_id($id_produk);

            if (!empty($existing_image)) {
                // Jika sudah ada, ambil id_gambar yang lama untuk di-update atau hapus file fisiknya dulu
                foreach ($existing_image as $img) {
                    $old_path = './uploads/produk/' . $img['nama_gambar'];
                    if (file_exists($old_path)) {
                        unlink($old_path);
                    }
                    // Hapus data lama di database agar tidak duplikat
                    $this->produk_gambar_model->hapus($img['id_gambar']);
                }
            }

            // Simpan data gambar baru
            $data = [
                'nama_gambar' => $upload_data['file_name'],
                'produk_id'   => $id_produk
            ];

            $simpan = $this->produk_gambar_model->tambah($data);

            if ($simpan !== false) {
                $jumlah_berhasil++;
            } else {
                $path = $upload_data['full_path'];
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
    }

    return $jumlah_berhasil;
}

    
    public function ubah_produk($id)
    {
        // check dulu apakah id dengan produk ada?
        $produk = $this->produk_model->get_by_id($id);
        if ($produk) {
            $this->form_validation->set_rules('nama_produk', 'Nama produk', 'required');
            $this->form_validation->set_rules('kategori_produk', 'Kategori', 'required');
            if ($this->form_validation->run() !== FALSE) {
                $this->__ubah_produk($id);
            } else {
                $data['title'] = 'Ubah produk';
                $data['produk'] = $produk;
                $data['list_kategori'] = $this->produk_kategori_model->get_all();
                $data['gambar_model'] = $this->produk_gambar_model;
                $this->load->view('administrator/templates/header', $data);
                $this->load->view('administrator/templates/sidebar');
                $this->load->view('administrator/produk/ubah_produk', $data);
                $this->load->view('administrator/templates/footer');
            }
        } else {
            $this->session->set_flashdata('message', '
            <div class="alert alert-danger d-flex align-items-center alert-dismissible fade show" role="alert">
                <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Danger:">
                    <use xlink:href="#exclamation-triangle-fill" />
                </svg>
                <div>
                    produk tidak ditemukan!!
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            ');
            redirect('administrator/produk');
        }
    }

    private function __ubah_produk($id)
{
    $data = [
        'nama' => ucwords($this->input->post('nama_produk')),
        'categori_id' => $this->input->post('kategori_produk'),
        'harga' => $this->input->post('harga_produk'),
        'stok' => $this->input->post('stok_produk'),
        'deskripsi' => ucfirst($this->input->post('deskripsi_produk'))
    ];

    // Simpan data produk
    $ubah = $this->produk_model->ubah($data, $id);

    // Periksa apakah pengguna memilih gambar baru
    $ada_gambar_baru = false;

    if (isset($_FILES['gambar_produk']['name'])) {
        foreach ($_FILES['gambar_produk']['name'] as $nama_file) {
            if (!empty($nama_file)) {
                $ada_gambar_baru = true;
                break;
            }
        }
    }

    $gambar_berhasil = true;

    if ($ada_gambar_baru) {
        // Ambil gambar lama
        $gambar_lama = $this->produk_gambar_model
            ->get_by_produk_id($id);

        $count = count($_FILES['gambar_produk']['name']);

        // Upload gambar baru
        $jumlah_berhasil = $this->__produk_gambar_upload(
            $count,
            $id
        );

        if ($jumlah_berhasil > 0) {
            // Hapus gambar lama setelah gambar baru berhasil diupload
            foreach ($gambar_lama as $gambar) {
                $path = FCPATH . 'uploads/produk/'
                    . $gambar['nama_gambar'];

                if (file_exists($path)) {
                    unlink($path);
                }

                $this->produk_gambar_model
                    ->hapus($gambar['id_gambar']);
            }
        } else {
            $gambar_berhasil = false;
        }
    }

    if ($ubah && $gambar_berhasil) {
        $this->session->set_flashdata(
            'message',
            '<div class="alert alert-success">Berhasil mengubah produk!</div>'
        );
    } else {
        $this->session->set_flashdata(
            'message',
            '<div class="alert alert-danger">Gagal mengubah produk atau mengunggah gambar. Periksa format gambar dan folder uploads/produk/.</div>'
        );
    }

    redirect('administrator/produk');
}

    public function hapus_produk($id)
    {
        // check apakah ada produk
        $produk = $this->produk_model->get_by_id($id);
        if ($produk) {
            // ambil data gambar
            $list_gambar = $this->produk_gambar_model->get_by_produk_id($id);
            foreach ($list_gambar as $gambar) {
                $id_gambar = $gambar['id_gambar'];
                $nama_gambar = $gambar['nama_gambar'];
                // hapus gambar di database
                $this->produk_gambar_model->hapus($id_gambar);
                // hapus gambar di file
                $path = './uploads/produk/' . $nama_gambar;
                unlink($path);
            }
            // hapus produk di database
            $this->produk_model->hapus($id);
            $this->session->set_flashdata('message', '
            <div class="alert alert-success d-flex align-items-center alert-dismissible fade show"
                role="alert">
                <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img"
                    aria-label="Success:">
                    <use xlink:href="#check-circle-fill" />
                </svg>
                <div>
                    Berhasil menghapus produk!!
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"
                    aria-label="Close"></button>
            </div>
            ');
            redirect('administrator/produk');
        } else {
            $this->session->set_flashdata('message', '
            <div class="alert alert-danger d-flex align-items-center alert-dismissible fade show"
                role="alert">
                <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img"
                    aria-label="Danger:">
                    <use xlink:href="#exclamation-triangle-fill" />
                </svg>
                <div>
                    produk tidak ditemukan!!
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"
                    aria-label="Close"></button>
            </div>
            ');
            redirect('administrator/produk');
        }
    }

    public function hapus_gambar($id_gambar, $id_produk)
{
    // Ambil data gambar berdasarkan ID gambar
    $gambar = $this->produk_gambar_model->get_by_id($id_gambar);

    // Pastikan gambar milik produk yang sesuai
    if ($gambar && $gambar['produk_id'] == $id_produk) {

        // Hapus file gambar dari folder
        $path = FCPATH . 'uploads/produk/' . $gambar['nama_gambar'];

        if (file_exists($path)) {
            unlink($path);
        }

        // Hapus data gambar dari database
        $this->produk_gambar_model->hapus($id_gambar);

        $this->session->set_flashdata(
            'message',
            '<div class="alert alert-success">Foto berhasil dihapus!</div>'
        );

    } else {
        $this->session->set_flashdata(
            'message',
            '<div class="alert alert-danger">Foto tidak ditemukan!</div>'
        );
    }

    // Kembali ke halaman ubah produk
    redirect('administrator/produk/ubah/' . $id_produk);
}
}