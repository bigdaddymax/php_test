<script>
    function showProperty(id){
        window.location.replace('/property/id/'+id);
    }
    
    function deleteSearchWord(word) {
        
        $.get('/search/delete/'+word)
        .done(function(data){
            $("#"+word).remove();
            window.location.replace('/');
        });
    }
</script>
<style>
    .search {
        margin-bottom: 20px;
    }

    .search-words {
        margin-left: 10px;
    }
</style>
<!-- List container -->
<div class="row">
    <?php
    if (empty($propertyList)) {
        echo '<div class="alert alert-info"><h3>No records found in DB.</h3></div>';
    } else {
        ?>
        <div class="col-lg-4 col-lg-offset-4 search">
            <form role="form" method="post" action="/search" class="search">
                <div class="input-group">
                    <input type="text" class="form-control" id="search" name="search" value ="<?php echo $search; ?>" placeholder="Search by City, Address, ZIP or State">
                    <span class="input-group-btn">
                        <button class="btn btn-default" type="submit"><span class="glyphicon glyphicon-search"></span></button>
                    </span>
                </div><!-- /input-group -->
            </form>
            <?php
            if (!empty($_SESSION['filter']['where'])) {
                ?>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        Search words:
                    </div>
                    <div class="panel-body">
                        <?php
                        foreach ($_SESSION['filter']['where'] as $key => $value) {
                            echo '<span class="label label-warning search-words" id="' . $key . '"><a href="javascript:deleteSearchWord(\'' . $key . '\')">&times</a> ' . $key . ': ' . $value . '</span>' . PHP_EOL;
                        }
                        ?>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>

        <div>
            <table class="table table-hover table-striped">
                <tr>
                    <th>Address</th>
                    <th>City</th>
                    <th>ZIP</th>
                    <th>State</th>
                    <th>Last sale date</th>
                    <th>Last sale price</th>
                </tr>
                <?php
                foreach ($propertyList as $row) {
                    if ($row->saleHistory) {
                        $saleHistory = $row->saleHistory;
                    }
                    echo '<tr onClick = "showProperty(' . $row->propertyId . ')">' .
                    '<td>' . $row->address . '</td>' .
                    '<td>' . $row->city . '</td>' .
                    '<td>' . $row->zip . '</td>' .
                    '<td>' . $row->state . '</td>' .
                    '<td>' . (($saleHistory[0]->saleDate) ? $saleHistory[0]->saleDate : 'No data') . '</td>' .
                    '<td>' . (($saleHistory[0]->salePrice) ? $saleHistory[0]->salePrice : 'No data') . '</td></tr>' . PHP_EOL;
                }
                ?>
            </table>

        </div>
        <ul class="pagination">
            <?php
            if ($pages > 1) {
                for ($i = 1; $i <= $pages; $i++) {
                    $class = '';
                    if ($i == $activePage) {
                        $class = 'class="active"';
                    }
                    echo '<li ' . $class . '><a href="/show/page/' . $i . '">' . $i . '</a></li>';
                }
            }
            ?>
        </ul>
        <?php
    }
    ?>
</div>
<!-- / List container -->