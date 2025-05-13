{{-- resources/views/partials/stock-opname-table.blade.php --}}
<table class="table align-items-center mb-0">
    <thead>
      <tr>
        @foreach([
          'Nomor Form',
          'Tanggal',
          'Jam',
          'Location',
          'Warehouse',
          'Nama Part',
          'Nomor Part',
          'Satuan',
          'Quantity Good',
          'Quantity Reject',
          'Quantity Repair',
          'Gambar'
        ] as $header)
          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">{{ $header }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($stockOpnameResults as $result)
        <tr>
          <td>{{ $result->nomor_form }}</td>
          <td>{{ $result->tanggal }}</td>
          <td>{{ $result->jam }}</td>
          <td>{{ $result->location }}</td>
          <td>{{ $result->warehouse }}</td>
          <td>{{ $result->nama_part }}</td>
          <td>{{ $result->nomor_part }}</td>
          <td>{{ $result->satuan }}</td>
          <td>{{ $result->quantity_good }}</td>
          <td>{{ $result->quantity_reject }}</td>
          <td>{{ $result->quantity_repair }}</td>
          <td>
            @include('components.file-preview', ['filePath' => $result->image_path])
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>