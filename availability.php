<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>施設の空き状況</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 50px;
        }
        .table-container {
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <a class="navbar-brand" href="#">施設リクエストシステム</a>
    </nav>

    <div class="container">
        <h2 class="text-center">施設の空き状況</h2>

        <form id="search-form" class="mb-4">
            <div class="form-row">
                <div class="col-md-4 mb-3">
                    <label for="building_name">建物名</label>
                    <input type="text" class="form-control" id="building_name" name="building_name">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="room_name">部屋名</label>
                    <input type="text" class="form-control" id="room_name" name="room_name">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="date">日付</label>
                    <input type="date" class="form-control" id="date" name="date">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">検索</button>
        </form>

        <div class="table-container">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>建物名</th>
                        <th>部屋名</th>
                        <th>日付</th>
                        <th>利用可能開始時間</th>
                        <th>利用可能終了時間</th>
                    </tr>
                </thead>
                <tbody id="availability-table-body">
                    <!-- データがここに表示されます -->
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function fetchAvailability() {
            var buildingName = $('#building_name').val();
            var roomName = $('#room_name').val();
            var date = $('#date').val();

            $.ajax({
                url: 'get_availability.php',
                method: 'GET',
                dataType: 'json',
                data: {
                    building_name: buildingName,
                    room_name: roomName,
                    date: date
                },
                success: function(data) {
                    var tbody = $('#availability-table-body');
                    tbody.empty();

                    data.forEach(function(row) {
                        var tr = $('<tr>');
                        tr.append($('<td>').text(row.building_name));
                        tr.append($('<td>').text(row.room_name));
                        tr.append($('<td>').text(row.date));
                        tr.append($('<td>').text(row.available_start_time));
                        tr.append($('<td>').text(row.available_end_time));
                        tbody.append(tr);
                    });
                }
            });
        }

        $(document).ready(function() {
            fetchAvailability();

            $('#search-form').on('submit', function(e) {
                e.preventDefault();
                fetchAvailability();
            });

            // 1分ごとにデータを更新
            setInterval(fetchAvailability, 60000);
        });
    </script>
</body>
</html>